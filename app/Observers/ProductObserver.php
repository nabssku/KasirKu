<?php

namespace App\Observers;

use App\Jobs\LowStockNotificationJob;
use App\Models\Product;

class ProductObserver
{
    public function updated(Product $product): void
    {
        if ($product->wasChanged('stock') && $product->stock <= $product->min_stock) {
            LowStockNotificationJob::dispatch($product);
        }
    }

    public function created(Product $product): void
    {
        if ($product->stock <= $product->min_stock) {
            LowStockNotificationJob::dispatch($product);
        }
    }
}
