<?php

namespace App\Listeners;

use App\Events\TransactionCompleted;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogActivityListener implements ShouldQueue
{
    public function handle(TransactionCompleted $event): void
    {
        ActivityLog::create([
            'tenant_id' => $event->transaction->tenant_id,
            'user_id' => $event->transaction->user_id,
            'action' => 'transaction_completed',
            'model_type' => get_class($event->transaction),
            'model_id' => $event->transaction->id,
            'properties' => [
                'invoice_number' => $event->transaction->invoice_number,
                'grand_total' => $event->transaction->grand_total,
            ],
            'ip_address' => request()->ip(),
        ]);
    }
}
