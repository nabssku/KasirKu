<?php

namespace App\Repositories\Eloquent;

use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Support\Str;

class TransactionRepository extends BaseRepository implements TransactionRepositoryInterface
{
    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }

    public function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd');
        
        $lastTransaction = $this->model
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if (!$lastTransaction) {
            return $prefix . '0001';
        }

        // Get the numeric part of the last invoice number and increment
        $lastNumber = (int) substr($lastTransaction->invoice_number, strlen($prefix));
        return $prefix . Str::padLeft($lastNumber + 1, 4, '0');
    }
}
