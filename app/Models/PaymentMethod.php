<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PaymentMethod extends Model
{
    use HasUuids;

    protected static function booted()
    {
        static::addGlobalScope('tenant_or_master', function ($builder) {
            if (app()->bound('current_tenant_id')) {
                $tenantId = app('current_tenant_id');
                if ($tenantId) {
                    $builder->where(function ($query) use ($tenantId) {
                        $query->where('tenant_id', $tenantId)
                              ->orWhereNull('tenant_id');
                    });
                }
            }
        });

        static::creating(function ($model) {
            if (app()->bound('current_tenant_id')) {
                $tenantId = app('current_tenant_id');
                if ($tenantId && !$model->tenant_id) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }
    
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'category',
        'icon',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'outlet_payment_methods')
            ->withPivot(['id', 'is_enabled', 'config'])
            ->withTimestamps();
    }
}
