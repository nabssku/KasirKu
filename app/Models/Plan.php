<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price',
        'billing_cycle',
        'max_outlets',
        'max_users',
        'max_products',
        'max_categories',
        'max_ingredients',
        'max_modifiers',
        'max_customers',
        'max_tables',
        'trial_days',
        'is_active',
        'description',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(string $key): bool
    {
        return $this->features()->where('feature_key', $key)->where('feature_value', 'true')->exists();
    }

    public function getFeatureValue(string $key): ?string
    {
        return $this->features()->where('feature_key', $key)->value('feature_value');
    }
}
