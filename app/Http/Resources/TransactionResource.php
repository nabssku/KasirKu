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
            'subtotal'       => (float) $this->subtotal,
            'tax'            => (float) $this->tax,
            'discount'       => (float) $this->discount,
            'grand_total'    => (float) $this->grand_total,
            'paid_amount'    => (float) $this->paid_amount,
            'change_amount'  => (float) $this->change_amount,
            'payment_method' => $this->whenLoaded('payments', fn() => $this->payments->first()?->payment_method),
            'status'         => $this->status,
            'notes'          => $this->notes,
            'cashier'        => $this->whenLoaded('user', fn() => [
                'id'   => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'customer'       => $this->whenLoaded('customer', fn() => $this->customer ? [
                'id'    => $this->customer->id,
                'name'  => $this->customer->name,
                'phone' => $this->customer->phone,
            ] : null),
            'items'          => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id'           => $item->id,
                'product_id'   => $item->product_id,
                'product_name' => $item->product_name,
                'quantity'     => $item->quantity,
                'price'        => (float) $item->price,
                'subtotal'     => (float) $item->subtotal,
            ])),
            'receipt_settings' => $this->whenLoaded('outlet', fn() => $this->outlet?->receipt_settings),
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
