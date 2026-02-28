<?php

namespace App\Models;

use App\Core\Traits\BelongsToOutlet;
use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, BelongsToOutlet;

    protected $appends = ['report'];

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'opened_by',
        'closed_by',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'cash_difference',
        'status',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'opening_cash'    => 'decimal:2',
        'closing_cash'    => 'decimal:2',
        'expected_cash'   => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function cashDrawerLogs(): HasMany
    {
        return $this->hasMany(CashDrawerLog::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function getReportAttribute(): array
    {
        $transactions = $this->transactions()->with(['payments'])->get();
        
        $grossSales = 0;
        $refundTotal = 0;
        $paymentBreakdown = [];

        foreach ($transactions as $tx) {
            if ($tx->status === 'completed') {
                $grossSales += (float) $tx->grand_total;
                foreach ($tx->payments as $payment) {
                    $method = $payment->payment_method;
                    $paymentBreakdown[$method] = ($paymentBreakdown[$method] ?? 0) + (float) $payment->amount;
                }
            } elseif ($tx->status === 'refunded') {
                $refundTotal += (float) $tx->grand_total;
                foreach ($tx->payments as $payment) {
                    $method = $payment->payment_method;
                    $paymentBreakdown[$method] = ($paymentBreakdown[$method] ?? 0) - (float) $payment->amount;
                }
            }
        }

        $cashIn = (float) $this->cashDrawerLogs()->where('type', 'in')->sum('amount');
        $cashOut = (float) $this->cashDrawerLogs()->where('type', 'out')->sum('amount');

        $netSales = $grossSales - $refundTotal;
        $cashSalesNet = $paymentBreakdown['cash'] ?? 0;
        
        $expectedCash = (float) $this->opening_cash + $cashSalesNet + $cashIn - $cashOut;
        $difference = $this->closing_cash !== null ? (float) $this->closing_cash - $expectedCash : 0;

        $threshold = 50000;
        $discrepancyStatus = 'OK';
        if ($difference < 0) {
            $discrepancyStatus = abs($difference) > $threshold ? 'Requires Approval' : 'Shortage';
        } elseif ($difference > 0) {
            $discrepancyStatus = 'Over';
        }

        return [
            'gross_sales' => $grossSales,
            'refund_total' => $refundTotal,
            'net_sales' => $netSales,
            'payment_breakdown' => $paymentBreakdown,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $expectedCash,
            'actual_cash' => (float) $this->closing_cash,
            'difference' => $difference,
            'discrepancy_status' => $discrepancyStatus,
            'opened_by_name' => $this->openedBy?->name,
            'closed_by_name' => $this->closedBy?->name,
        ];
    }
}
