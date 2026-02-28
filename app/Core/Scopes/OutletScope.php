<?php

namespace App\Core\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OutletScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        // If not logged in, or is Super Admin / Owner, don't scope by outlet (they see all)
        if (!$user || $user->hasAnyRole(['super_admin', 'owner'])) {
            return;
        }

        // For Admin, Cashier, Kitchen, etc., scope by their assigned outlet_id
        if ($user->outlet_id) {
            $table = $model->getTable();
            
            // For some models (like Product), we might want to allow items with null outlet_id (available to all)
            // But for most transactional data (Transaction, Shift, etc), it should be exact.
            // We can check if the model has a property to allow nulls or just do exact match.
            if (method_exists($model, 'shouldIncludeGlobalOutlets') && $model->shouldIncludeGlobalOutlets()) {
                $builder->where(function ($q) use ($table, $user) {
                    $q->where($table . '.outlet_id', $user->outlet_id)
                      ->orWhereNull($table . '.outlet_id');
                });
            } else {
                $builder->where($table . '.outlet_id', $user->outlet_id);
            }
        }
    }
}
