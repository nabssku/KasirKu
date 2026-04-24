<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'domain',
        'email',
        'settings',
        'status',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    protected $casts = [
        'settings' => 'json',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    protected $appends = ['status_subscription', 'status_subcription', 'plan_id'];

    public function getStatusSubscriptionAttribute(): string
    {
        // Try to get status from latest subscription, fallback to tenant status
        return $this->latestSubscription?->status ?? $this->status;
    }

    public function getStatusSubcriptionAttribute(): string
    {
        return $this->getStatusSubscriptionAttribute();
    }

    public function getPlanIdAttribute()
    {
        return $this->latestSubscription?->plan_id;
    }

    public function isOnboardingCompleted(): bool
    {
        return (bool) ($this->settings['onboarding_completed'] ?? false);
    }

    public function getOnboardingStep(): string
    {
        return $this->settings['onboarding_step'] ?? 'initial';
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->whereIn('status', ['active', 'trial']);
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }
}
