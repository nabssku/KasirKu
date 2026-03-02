<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class SyncBayarGgPayments extends Command
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
    protected $description = 'Sync pending bayar.gg payments and activate subscriptions';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService)
    {
        $this->info('Starting bayar.gg payment synchronization...');
        
        $synced = $subscriptionService->syncPendingPayments();
        
        if ($synced > 0) {
            $this->success("Successfully synced {$synced} payments.");
        } else {
            $this->info('No pending payments to sync.');
        }
    }

    protected function success($message)
    {
        $this->line("<info>{$message}</info>");
    }
}
