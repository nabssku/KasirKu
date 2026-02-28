<?php

namespace App\Core\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            static::recordAuditLog($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $oldValues = array_intersect_key($model->getOriginal(), $model->getDirty());
            $newValues = $model->getDirty();

            // Filter out sensitive fields
            $sensitiveFields = ['password', 'remember_token'];
            foreach ($sensitiveFields as $field) {
                unset($oldValues[$field], $newValues[$field]);
            }

            if (!empty($newValues)) {
                static::recordAuditLog($model, 'updated', $oldValues, $newValues);
            }
        });

        static::deleted(function (Model $model) {
            static::recordAuditLog($model, 'deleted', $model->getAttributes(), null);
        });
    }

    protected static function recordAuditLog(Model $model, string $action, ?array $oldValues, ?array $newValues)
    {
        $user = Auth::user();
        
        // Don't log if no tenant_id available (unless it's a system action we want to track anyway)
        $tenantId = $model->tenant_id ?? ($user->tenant_id ?? null);
        
        if (!$tenantId) return;

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => Auth::id(),
            'outlet_id' => $model->outlet_id ?? ($user->outlet_id ?? null),
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request() ? request()->ip() : null,
            'user_agent' => request() ? request()->userAgent() : 'System',
        ]);
    }
}
