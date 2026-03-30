<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Discount extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_purchase_amount',
        'max_uses_total',
        'uses_count',
        'max_uses_per_user',
        'applicable_plan_ids',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'value'               => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'applicable_plan_ids' => 'array',
        'is_active'           => 'boolean',
        'valid_from'          => 'datetime',
        'valid_until'         => 'datetime',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(DiscountUsage::class);
    }

    public function isValid(?int $planId = null, ?float $purchaseAmount = null, ?string $tenantId = null): bool
    {
        if (!$this->is_active) return false;

        $now = Carbon::now();
        if ($this->valid_from && $this->valid_from->isFuture()) return false;
        if ($this->valid_until && $this->valid_until->isPast()) return false;

        if ($this->max_uses_total !== null && $this->uses_count >= $this->max_uses_total) return false;

        if ($this->applicable_plan_ids && $planId !== null) {
             if (!in_array($planId, $this->applicable_plan_ids)) return false;
        }

        if ($purchaseAmount !== null && $purchaseAmount < $this->min_purchase_amount) return false;

        if ($tenantId !== null) {
            $userUsage = $this->usages()->where('tenant_id', $tenantId)->count();
            if ($userUsage >= $this->max_uses_per_user) return false;
        }

        return true;
    }

    public function calculateDiscount(float $amount): float
    {
        if ($this->type === 'percentage') {
            return ($amount * $this->value) / 100;
        }
        return min($this->value, $amount);
    }
}
