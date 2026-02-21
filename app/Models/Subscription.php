<?php

namespace App\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'starts_at'     => 'datetime',
        'ends_at'       => 'datetime',
    ];

    protected $appends = ['days_remaining', 'is_active', 'total_days'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return match($this->status) {
            'trial'  => $this->trial_ends_at?->isFuture() ?? false,
            'active' => $this->ends_at?->isFuture() ?? false,
            default  => false,
        };
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    public function getDaysRemainingAttribute(): int
    {
        $endDate = match($this->status) {
            'trial'  => $this->trial_ends_at,
            default  => $this->ends_at,
        };

        if (!$endDate) return 0;

        $remaining = (int) now()->diffInDays($endDate, false);
        return max(0, $remaining);
    }

    public function getTotalDaysAttribute(): int
    {
        if (!$this->starts_at) return 0;

        $endDate = match($this->status) {
            'trial'  => $this->trial_ends_at,
            default  => $this->ends_at,
        };

        if (!$endDate) return 0;

        return max(1, (int) $this->starts_at->diffInDays($endDate));
    }
}
