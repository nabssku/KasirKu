<?php

namespace App\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'transaction_id',
        'outlet_id',
        'type',
        'amount',
        'gateway',
        'gateway_order_id',
        'invoice_id',
        'payment_url',
        'final_amount',
        'snap_token',
        'gateway_transaction_id',
        'status',
        'gateway_payload',
        'paid_at',
        'expires_at',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'final_amount'    => 'integer',
        'gateway_payload' => 'array',
        'paid_at'         => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
