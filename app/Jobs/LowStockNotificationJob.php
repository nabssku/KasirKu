<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class LowStockNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Product $product
    ) {}

    public function handle(): void
    {
        // In a real SaaS, this would send an email or push notification to the tenant owner
        Log::info("Low stock alert for Product: {$this->product->name} (SKU: {$this->product->sku}) in Tenant: {$this->product->tenant_id}. Current stock: {$this->product->stock}");
        
        // Example: Notification::send($owner, new LowStockNotification($this->product));
    }
}
