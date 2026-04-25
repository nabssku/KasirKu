<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'invoice_number' => $this->invoice_number,
            'type'           => $this->type,
            'table_id'       => $this->table_id,
            'subtotal'       => (float) $this->subtotal,
            'tax'            => (float) $this->tax,
            'discount'       => (float) $this->discount,
            'grand_total'    => (float) $this->grand_total,
            'paid_amount'    => (float) $this->paid_amount,
            'change_amount'  => (float) $this->change_amount,
            'payment_method' => $this->whenLoaded('payments', function() {
                $payment = $this->payments->first();
                return $payment?->payment_method_name ?? $payment?->payment_method;
            }),
            'status'         => $this->status,
            'notes'          => $this->notes,
            'cancel_reason'  => $this->cancel_reason,
            'cancelled_at'   => $this->cancelled_at?->toISOString(),
            'cancelled_by'   => $this->whenLoaded('cancelledBy', fn() => [
                'id'   => $this->cancelledBy?->id,
                'name' => $this->cancelledBy?->name,
            ]),
            'cashier'        => $this->whenLoaded('user', fn() => [
                'id'   => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'customer'       => $this->whenLoaded('customer', fn() => $this->customer ? [
                'id'    => $this->customer->id,
                'name'  => $this->customer->name,
                'phone' => $this->customer->phone,
            ] : null),
            'table'          => $this->whenLoaded('table', fn() => $this->table ? [
                'id'   => $this->table->id,
                'name' => $this->table->name,
            ] : null),
            'items'          => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id'           => $item->id,
                'product_id'   => $item->product_id,
                'product_name' => $item->product_name,
                'quantity'     => $item->quantity,
                'price'        => (float) $item->price,
                'discount'     => (float) $item->discount,
                'subtotal'     => (float) $item->subtotal,
                'notes'        => $item->notes,
                'modifiers'    => $item->modifiers->map(fn($m) => [
                    'modifier_id' => $m->modifier_id,
                    'name'        => $m->modifier_name,
                    'price'       => (float) $m->price,
                ])->values()->all(),
            ])),
            'receipt_settings' => $this->whenLoaded('outlet', fn() => $this->outlet?->receipt_settings),
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
