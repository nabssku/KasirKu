<?php

namespace App\Models;

use App\Core\Traits\Auditable;

use App\Core\Traits\BelongsToOutlet;
use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestaurantTable extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, BelongsToOutlet, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'name',
        'capacity',
        'status',
        'floor',
        'sort_order',
        'qr_token',
        'qr_enabled',
        'qr_generated_at',
    ];

    protected $appends = [
        'qr_url',
    ];

    protected $casts = [
        'capacity'        => 'integer',
        'sort_order'      => 'integer',
        'qr_enabled'      => 'boolean',
        'qr_generated_at' => 'datetime',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function activeTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'table_id')->whereIn('status', ['pending', 'in_progress', 'pending_payment']);
    }

    public function selfOrderSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SelfOrderSession::class, 'table_id');
    }

    public function hasQrEnabled(): bool
    {
        return $this->qr_enabled && !empty($this->qr_token);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function getQrUrlAttribute(): ?string
    {
        if (empty($this->qr_token)) return null;

        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
        return "{$frontendUrl}/menu/table/{$this->qr_token}";
    }
}
