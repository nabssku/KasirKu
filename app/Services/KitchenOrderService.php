<?php

namespace App\Services;

use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenOrderService
{
    /**
     * Create a kitchen order from a completed transaction.
     */
    public function createFromTransaction(Transaction $transaction): KitchenOrder
    {
        return DB::transaction(function () use ($transaction) {
            $orderCode = strtoupper(substr($transaction->invoice_number, -6));

            $kitchenOrder = KitchenOrder::create([
                'tenant_id'      => $transaction->tenant_id,
                'outlet_id'      => $transaction->outlet_id,
                'transaction_id' => $transaction->id,
                'status'         => 'queued',
                'order_code'     => '#' . $orderCode,
                'table_name'     => $transaction->table?->name,
                'type'           => $transaction->type ?? 'dine_in',
                'notes'          => $transaction->notes,
            ]);

            foreach ($transaction->items as $item) {
                // Build modifier notes string
                $modifierNotes = $item->modifiers?->map(fn ($m) => $m->modifier_name)->join(', ');

                KitchenOrderItem::create([
                    'kitchen_order_id'   => $kitchenOrder->id,
                    'transaction_item_id' => $item->id,
                    'product_name'       => $item->product_name,
                    'quantity'           => $item->quantity,
                    'modifier_notes'     => $modifierNotes,
                    'status'             => 'queued',
                ]);
            }

            return $kitchenOrder->load('items');
        });
    }

    /**
     * Transition a kitchen order to a new status.
     */
    public function updateStatus(KitchenOrder $kitchenOrder, string $newStatus): KitchenOrder
    {
        if (!$kitchenOrder->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from '{$kitchenOrder->status}' to '{$newStatus}'.",
            ]);
        }

        $timestamps = match ($newStatus) {
            'cooking' => ['accepted_at' => now()],
            'ready'   => ['ready_at'    => now()],
            'served'  => ['served_at'   => now()],
            default   => [],
        };

        $kitchenOrder->update(array_merge(['status' => $newStatus], $timestamps));

        // Cascade to items if transitioning to served
        if ($newStatus === 'served') {
            $kitchenOrder->items()->update(['status' => 'ready']);
        }

        return $kitchenOrder->fresh(['items']);
    }

    public function getQueueByOutlet(string $outletId, array $statuses = ['queued', 'cooking', 'ready'])
    {
        return KitchenOrder::with(['items'])
            ->where('outlet_id', $outletId)
            ->whereIn('status', $statuses)
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
