<?php

namespace App\Core\Traits;

use App\Core\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model) {
            if (app()->bound('current_tenant_id')) {
                $tenantId = app('current_tenant_id');
                if ($tenantId && !$model->tenant_id) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }
}
