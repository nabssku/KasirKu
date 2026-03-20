<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use App\Services\SelfOrderService;
use Illuminate\Console\Command;

class SyncGatewayPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-payments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync pending gateway payments for subscriptions and self-orders';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService, SelfOrderService $selfOrderService)
    {
        $this->info('Starting payment gateway synchronization...');
        
        $this->info('Syncing subscriptions...');
        $subSynced = $subscriptionService->syncPendingPayments();
        
        $this->info('Syncing self-orders...');
        $selfSynced = $selfOrderService->syncPendingPayments();
        
        $total = $subSynced + $selfSynced;
        
        if ($total > 0) {
            $this->info("Successfully synced {$total} payments ({$subSynced} subscriptions, {$selfSynced} self-orders).");
        } else {
            $this->info('No pending payments to sync.');
        }
    }
}
