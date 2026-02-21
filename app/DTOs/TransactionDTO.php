<?php

namespace App\DTOs;

class TransactionItemDTO
{
    public function __construct(
        public string $product_id,
        public int $quantity,
        public float $price,
        public array $modifiers = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            product_id: $data['product_id'],
            quantity: $data['quantity'],
            price: $data['price'],
            modifiers: $data['modifiers'] ?? []
        );
    }
}

class TransactionDTO
{
    /** @param TransactionItemDTO[] $items */
    public function __construct(
        public array $items,
        public ?string $customer_id = null,
        public ?string $table_id = null,
        public ?string $shift_id = null,
        public string $type = 'dine_in',
        public float $discount = 0,
        public float $paid_amount = 0,
        public string $payment_method = 'cash',
        public ?string $notes = null,
        public array $payments = []
    ) {}

    public static function fromRequest(array $data): self
    {
        $items = array_map(fn($item) => TransactionItemDTO::fromArray($item), $data['items']);

        return new self(
            items: $items,
            customer_id: $data['customer_id'] ?? null,
            table_id: $data['table_id'] ?? null,
            shift_id: $data['shift_id'] ?? null,
            type: $data['type'] ?? 'dine_in',
            discount: $data['discount'] ?? 0,
            paid_amount: $data['paid_amount'] ?? 0,
            payment_method: $data['payment_method'] ?? 'cash',
            notes: $data['notes'] ?? null,
            payments: $data['payments'] ?? []
        );
    }
}
