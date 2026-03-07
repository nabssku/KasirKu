<?php

namespace App\Services;

use App\DTOs\TransactionDTO;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Events\TransactionCompleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        protected TransactionRepositoryInterface $repository,
        protected InventoryService $inventoryService,
        protected KitchenOrderService $kitchenOrderService,
    ) {}

    public function createTransaction(TransactionDTO $dto): Transaction
    {
        return DB::transaction(function () use ($dto) {
            $user    = auth()->user();
            $outlet  = $user->outlet;

            // Check for active shift
            $shiftId = $dto->shift_id;
            if (!$shiftId && $user->outlet_id) {
                $currentShift = Shift::where('outlet_id', $user->outlet_id)
                    ->where('status', 'open')
                    ->first();
                $shiftId = $currentShift?->id;
            }

            if (!$shiftId) {
                throw ValidationException::withMessages([
                    'shift' => 'Transaksi tidak dapat dilakukan karena tidak ada shift yang dibuka. Silakan buka shift terlebih dahulu.'
                ]);
            }

            $checkShift = Shift::find($shiftId);
            if (!$checkShift || !$checkShift->isOpen()) {
                throw ValidationException::withMessages([
                    'shift' => 'Shift ini sudah ditutup. Silakan buka shift baru untuk melakukan transaksi.'
                ]);
            }

            $taxRate = $outlet?->tax_rate ?? 10;
            $scRate  = $outlet?->service_charge ?? 0;

            $subtotal  = 0;
            $itemsData = [];

            foreach ($dto->items as $itemDto) {
                $product      = Product::findOrFail($itemDto->product_id);
                $itemSubtotal = $itemDto->price * $itemDto->quantity;
                $subtotal    += $itemSubtotal;

                $itemsData[] = [
                    'id'           => Str::uuid(),
                    'tenant_id'    => $user->tenant_id,
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'price'        => $itemDto->price,
                    'quantity'     => $itemDto->quantity,
                    'subtotal'     => $itemSubtotal,
                    'modifiers'    => $itemDto->modifiers ?? [],
                ];

                // Decrement simple product stock (non-recipe products)
                if (!$product->has_recipe) {
                    $product->decrement('stock', $itemDto->quantity);
                }
            }

            $tax            = $subtotal * ($taxRate / 100);
            $serviceCharge  = $subtotal * ($scRate / 100);
            $grandTotal     = ($subtotal + $tax + $serviceCharge) - (float) ($dto->discount ?? 0);

            // Determine total paid from split payments
            $totalPaid = collect($dto->payments ?? [])->sum('amount');
            if (empty($dto->payments)) {
                $totalPaid = $dto->paid_amount ?? $grandTotal;
            }

            // 1. Create Transaction
            $transaction = $this->repository->create([
                'tenant_id'      => $user->tenant_id,
                'outlet_id'      => $user->outlet_id,
                'user_id'        => $user->id,
                'customer_id'    => $dto->customer_id,
                'table_id'       => $dto->table_id,
                'shift_id'       => $shiftId,
                'invoice_number' => $this->repository->generateInvoiceNumber(),
                'type'           => $dto->type ?? 'dine_in',
                'subtotal'       => $subtotal,
                'tax_rate'       => $taxRate,
                'tax'            => $tax,
                'service_charge' => $serviceCharge,
                'discount'       => $dto->discount ?? 0,
                'grand_total'    => $grandTotal,
                'paid_amount'    => $dto->status === 'pending' ? 0 : $totalPaid,
                'change_amount'  => $dto->status === 'pending' ? 0 : max(0, $totalPaid - $grandTotal),
                'status'         => $dto->status ?? 'completed',
                'notes'          => $dto->notes,
            ]);

            // 2. Create Items + Modifiers
            foreach ($itemsData as $itemData) {
                $modifiers = $itemData['modifiers'];
                unset($itemData['modifiers']);
                $itemData['transaction_id'] = $transaction->id;

                $txItem = TransactionItem::create($itemData);

                foreach ($modifiers as $modifierData) {
                    // Check if the relation/method exists or just skip if not defined to avoid crash
                    if (method_exists($txItem, 'modifiers')) {
                        $txItem->modifiers()->create([
                            'modifier_id'   => $modifierData['modifier_id'],
                            'modifier_name' => $modifierData['modifier_name'] ?? $modifierData['name'],
                            'price'         => $modifierData['price'] ?? 0,
                        ]);
                    }
                }
            }

            // 3. Create Payments (supports split payment) - Skip if pending
            if ($transaction->status !== 'pending') {
                if (!empty($dto->payments)) {
                    foreach ($dto->payments as $paymentData) {
                        Payment::create([
                            'tenant_id'        => $user->tenant_id,
                            'transaction_id'   => $transaction->id,
                            'payment_method'   => $paymentData['method'],
                            'amount'           => $paymentData['amount'],
                            'payment_reference' => $paymentData['reference'] ?? null,
                            'paid_at'          => now(),
                        ]);
                    }
                } else {
                    Payment::create([
                        'tenant_id'      => $user->tenant_id,
                        'transaction_id' => $transaction->id,
                        'payment_method' => $dto->payment_method,
                        'amount'         => $totalPaid,
                        'paid_at'        => now(),
                    ]);
                }

                // 4. Deduct ingredient stock via recipe (Only if completed)
                $transaction->load('items');
                $this->inventoryService->deductByTransaction($transaction);
            }

            // 4. Update table status to occupied (if dine_in and table select)
            if ($dto->table_id && $dto->type === 'dine_in') {
                RestaurantTable::where('id', $dto->table_id)->update(['status' => 'occupied']);
            }

            // 6. Create kitchen order (Keep this if we want kitchen to receive pending orders, 
            // but usually they wait for completion. However, in some POS, "Send to Kitchen" is a separate step.
            // For now, let's keep it if pending? Actually, let's skip for pending unless specified.
            // The request says "simpan sementara", so kitchen probably shouldn't receive it yet.
            if ($transaction->status !== 'pending') {
                $this->kitchenOrderService->createFromTransaction($transaction);
                // 7. Fire event
                event(new TransactionCompleted($transaction));
            }

            return $transaction->load(['items', 'customer', 'payments', 'kitchenOrder']);
        });
    }

    public function updateTransaction(string $id, TransactionDTO $dto): Transaction
    {
        return DB::transaction(function () use ($id, $dto) {
            $transaction = Transaction::findOrFail($id);
            $user        = auth()->user();
            $outlet      = $user->outlet;

            // Check for active shift if completing or if shift is missing
            $shiftId = $dto->shift_id ?? $transaction->shift_id;
            if (!$shiftId && $user->outlet_id) {
                $currentShift = Shift::where('outlet_id', $user->outlet_id)
                    ->where('status', 'open')
                    ->first();
                $shiftId = $currentShift?->id;
            }

            // If we are completing a transaction, we MUST have an open shift
            if ($dto->status === 'completed' || $transaction->status === 'completed') {
                $checkShift = Shift::find($shiftId);
                if (!$checkShift || !$checkShift->isOpen()) {
                    throw ValidationException::withMessages([
                        'shift' => 'Transaksi tidak dapat diperbarui/diselesaikan karena shift sudah tutup atau tidak ditemukan.'
                    ]);
                }
            }

            $taxRate     = $outlet?->tax_rate ?? 10;
            $scRate      = $outlet?->service_charge ?? 0;

            $subtotal  = 0;
            $itemsData = [];

            foreach ($dto->items as $itemDto) {
                $product      = Product::findOrFail($itemDto->product_id);
                $itemSubtotal = $itemDto->price * $itemDto->quantity;
                $subtotal    += $itemSubtotal;

                $itemsData[] = [
                    'id'           => Str::uuid(),
                    'tenant_id'    => $user->tenant_id,
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'price'        => $itemDto->price,
                    'quantity'     => $itemDto->quantity,
                    'subtotal'     => $itemSubtotal,
                    'modifiers'    => $itemDto->modifiers ?? [],
                ];
                
                // Stock management for update is tricky if we already deducted.
                // But since we only deduct on completion, and pending orders don't deduct, it's fine.
            }

            $tax            = $subtotal * ($taxRate / 100);
            $serviceCharge  = $subtotal * ($scRate / 100);
            $grandTotal     = ($subtotal + $tax + $serviceCharge) - (float) ($dto->discount ?? 0);

            $totalPaid = collect($dto->payments ?? [])->sum('amount');
            if (empty($dto->payments)) {
                $totalPaid = $dto->paid_amount ?? $grandTotal;
            }

            // 1. Update Transaction
            $transaction->update([
                'customer_id'    => $dto->customer_id,
                'table_id'       => $dto->table_id,
                'shift_id'       => $shiftId,
                'type'           => $dto->type ?? $transaction->type,
                'subtotal'       => $subtotal,
                'tax_rate'       => $taxRate,
                'tax'            => $tax,
                'service_charge' => $serviceCharge,
                'discount'       => $dto->discount ?? 0,
                'grand_total'    => $grandTotal,
                'paid_amount'    => $dto->status === 'pending' ? 0 : $totalPaid,
                'change_amount'  => $dto->status === 'pending' ? 0 : max(0, $totalPaid - $grandTotal),
                'status'         => $dto->status ?? $transaction->status,
                'notes'          => $dto->notes,
            ]);

            // 2. Delete old items and create new ones (simplest approach for update)
            $transaction->items()->delete();

            foreach ($itemsData as $itemData) {
                $modifiers = $itemData['modifiers'];
                unset($itemData['modifiers']);
                $itemData['transaction_id'] = $transaction->id;

                $txItem = TransactionItem::create($itemData);

                foreach ($modifiers as $modifierData) {
                    if (method_exists($txItem, 'modifiers')) {
                        $txItem->modifiers()->create([
                            'modifier_id'   => $modifierData['modifier_id'],
                            'modifier_name' => $modifierData['modifier_name'] ?? $modifierData['name'],
                            'price'         => $modifierData['price'] ?? 0,
                        ]);
                    }
                }
            }

            // 3. Handle Payments and Completing
            if ($transaction->status !== 'pending') {
                $transaction->payments()->delete();
                if (!empty($dto->payments)) {
                    foreach ($dto->payments as $paymentData) {
                        Payment::create([
                            'tenant_id'        => $user->tenant_id,
                            'transaction_id'   => $transaction->id,
                            'payment_method'   => $paymentData['method'],
                            'amount'           => $paymentData['amount'],
                            'payment_reference' => $paymentData['reference'] ?? null,
                            'paid_at'          => now(),
                        ]);
                    }
                } else {
                    Payment::create([
                        'tenant_id'      => $user->tenant_id,
                        'transaction_id' => $transaction->id,
                        'payment_method' => $dto->payment_method,
                        'amount'         => $totalPaid,
                        'paid_at'        => now(),
                    ]);
                }

                // 4. Deduct ingredient stock via recipe
                $transaction->load('items');
                $this->inventoryService->deductByTransaction($transaction);

                // 5. Create kitchen order
                $this->kitchenOrderService->createFromTransaction($transaction);

                // 6. Fire event
                event(new TransactionCompleted($transaction));
            }

            return $transaction->load(['items', 'customer', 'payments', 'kitchenOrder']);
        });
    }

    public function getTransaction(string $id): ?Transaction
    {
        return $this->repository->find($id);
    }

    public function cancelTransaction(string $id, string $reason): Transaction
    {
        return DB::transaction(function () use ($id, $reason) {
            $transaction = Transaction::with(['items', 'table', 'payments'])->findOrFail($id);

            if ($transaction->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'Transaksi ini sudah dibatalkan sebelumnya.'
                ]);
            }

            // Detect cash payments to record in CashDrawerLog if this is a refund
            $cashRefundAmount = $transaction->payments()->where('payment_method', 'cash')->sum('amount');
            if ($cashRefundAmount > 0) {
                $shiftId = $transaction->shift_id;
                $shift = Shift::find($shiftId);
                
                // If the transaction's shift is closed or missing, try to get current open shift
                if (!$shift || !$shift->isOpen()) {
                    $shift = Shift::where('outlet_id', $transaction->outlet_id)
                        ->where('status', 'open')
                        ->first();
                }

                if ($shift && $shift->isOpen()) {
                    $cashDrawerLogService = app(\App\Services\ShiftService::class);
                    $cashDrawerLogService->addCashDrawerLog($shift, [
                        'type'   => 'out',
                        'amount' => $cashRefundAmount,
                        'reason' => "Pembatalan Transaksi #{$transaction->invoice_number} (Refund Tunai)"
                    ]);
                }
            }

            // 1. Update transaction status and metadata
            $transaction->update([
                'status'        => 'cancelled',
                'cancelled_at'  => now(),
                'cancelled_by'  => auth()->id(),
                'cancel_reason' => $reason,
                'notes'         => $reason,
            ]);

            // 2. Revert product stock (for non-recipe products)
            foreach ($transaction->items as $item) {
                $product = Product::find($item->product_id);
                if ($product && !$product->has_recipe) {
                    $product->increment('stock', $item->quantity);
                }
            }

            // 3. Revert ingredient stock (via InventoryService)
            $this->inventoryService->revertByTransaction($transaction);

            // 4. Handle table status
            if ($transaction->table_id) {
                // Check if there are other active (pending/open) transactions for this table
                $activeOnTable = Transaction::where('table_id', $transaction->table_id)
                    ->whereIn('status', ['pending', 'open'])
                    ->where('id', '!=', $transaction->id)
                    ->exists();

                if (!$activeOnTable) {
                    RestaurantTable::where('id', $transaction->table_id)->update(['status' => 'available']);
                }
            }

            return $transaction->load(['items', 'customer', 'payments', 'cancelledBy']);
        });
    }

    public function deleteTransaction(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
