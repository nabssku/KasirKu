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
        $count = $this->model->where('invoice_number', 'like', $prefix . '%')->count();
        return $prefix . Str::padLeft($count + 1, 4, '0');
    }
}
