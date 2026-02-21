<?php

namespace App\Services;

use App\DTOs\TransactionDTO;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Events\TransactionCompleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                'shift_id'       => $dto->shift_id,
                'invoice_number' => $this->repository->generateInvoiceNumber(),
                'type'           => $dto->type ?? 'dine_in',
                'subtotal'       => $subtotal,
                'tax_rate'       => $taxRate,
                'tax'            => $tax,
                'service_charge' => $serviceCharge,
                'discount'       => $dto->discount ?? 0,
                'grand_total'    => $grandTotal,
                'paid_amount'    => $totalPaid,
                'change_amount'  => max(0, $totalPaid - $grandTotal),
                'status'         => 'completed',
                'notes'          => $dto->notes,
            ]);

            // 2. Create Items + Modifiers
            foreach ($itemsData as $itemData) {
                $modifiers = $itemData['modifiers'];
                unset($itemData['modifiers']);
                $itemData['transaction_id'] = $transaction->id;

                $txItem = TransactionItem::create($itemData);

                foreach ($modifiers as $modifierData) {
                    $txItem->modifiers()->create([
                        'modifier_id'   => $modifierData['modifier_id'],
                        'modifier_name' => $modifierData['modifier_name'],
                        'price'         => $modifierData['price'] ?? 0,
                    ]);
                }
            }

            // 3. Create Payments (supports split payment)
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

            // 4. Update table status to occupied (if dine_in)
            if ($dto->table_id && $dto->type === 'dine_in') {
                RestaurantTable::where('id', $dto->table_id)->update(['status' => 'occupied']);
            }

            // 5. Deduct ingredient stock via recipe
            $transaction->load('items');
            $this->inventoryService->deductByTransaction($transaction);

            // 6. Create kitchen order
            $this->kitchenOrderService->createFromTransaction($transaction);

            // 7. Fire event
            event(new TransactionCompleted($transaction));

            return $transaction->load(['items', 'customer', 'payments', 'kitchenOrder']);
        });
    }

    public function getTransaction(string $id): ?Transaction
    {
        return $this->repository->find($id);
    }

    public function deleteTransaction(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
