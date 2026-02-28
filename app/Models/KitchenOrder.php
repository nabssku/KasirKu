<?php

namespace App\Models;

use App\Core\Traits\BelongsToOutlet;
use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenOrder extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, BelongsToOutlet;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'transaction_id',
        'status',
        'order_code',
        'table_name',
        'type',
        'notes',
        'accepted_at',
        'ready_at',
        'served_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'ready_at'    => 'datetime',
        'served_at'   => 'datetime',
    ];

    // Valid status transitions
    public const STATUS_TRANSITIONS = [
        'queued'  => ['cooking', 'cancelled'],
        'cooking' => ['ready'],
        'ready'   => ['served'],
        'served'  => [],
        'cancelled' => [],
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenOrderItem::class);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::STATUS_TRANSITIONS[$this->status] ?? []);
    }
}
