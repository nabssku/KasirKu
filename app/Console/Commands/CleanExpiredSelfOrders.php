<?php

namespace App\Console\Commands;

use App\Models\SelfOrderSession;
use App\Models\Transaction;
use Illuminate\Console\Command;

class CleanExpiredSelfOrders extends Command
{
    protected $signature   = 'self-order:clean-expired';
    protected $description = 'Cancel pending_payment transactions and expire sessions where payment has timed out.';

    public function handle(): void
    {
        $now = now();

        // 1. Cancel transactions where payment expired
        $cancelledCount = Transaction::where('status', 'pending_payment')
            ->where('payment_expires_at', '<', $now)
            ->update([
                'status'        => 'cancelled',
                'cancel_reason' => 'Pembayaran expired otomatis.',
                'cancelled_at'  => $now,
            ]);

        // 2. Expire stale sessions
        $expiredCount = SelfOrderSession::where('status', 'active')
            ->where('expires_at', '<', $now)
            ->update(['status' => 'expired']);

        $this->info("Cancelled {$cancelledCount} pending_payment transactions.");
        $this->info("Expired {$expiredCount} stale sessions.");
    }
}
