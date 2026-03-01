<?php

namespace App\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToOutlet;
use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, BelongsToOutlet, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'user_id',
        'customer_id',
        'table_id',
        'shift_id',
        'invoice_number',
        'type',
        'subtotal',
        'tax_rate',
        'tax',
        'service_charge',
        'discount',
        'grand_total',
        'paid_amount',
        'change_amount',
        'status',
        'notes',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'subtotal'       => 'decimal:2',
        'tax_rate'       => 'decimal:2',
        'tax'            => 'decimal:2',
        'service_charge' => 'decimal:2',
        'discount'       => 'decimal:2',
        'grand_total'    => 'decimal:2',
        'paid_amount'    => 'decimal:2',
        'change_amount'  => 'decimal:2',
        'cancelled_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function kitchenOrder(): HasOne
    {
        return $this->hasOne(KitchenOrder::class);
    }
}
