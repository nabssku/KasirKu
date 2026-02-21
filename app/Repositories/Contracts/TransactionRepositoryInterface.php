<?php

namespace App\Repositories\Contracts;

use App\Models\Transaction;

interface TransactionRepositoryInterface extends RepositoryInterface
{
    public function generateInvoiceNumber(): string;
}
