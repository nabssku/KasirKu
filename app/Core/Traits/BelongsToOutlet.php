<?php

namespace App\Core\Traits;

use App\Core\Scopes\OutletScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToOutlet
{
    /**
     * Boot the trait and register the global scope.
     *
     * @return void
     */
    protected static function bootBelongsToOutlet(): void
    {
        static::addGlobalScope(new OutletScope());

        static::creating(function (Model $model) {
            // Automatically set the outlet_id from the authenticated user
            // if it's not explicitly set and the user has an outlet assignment.
            if (auth()->check() && !$model->outlet_id) {
                $user = auth()->user();
                if ($user->outlet_id) {
                    $model->outlet_id = $user->outlet_id;
                }
            }
        });
    }

    /**
     * Check if the model can be viewed by all outlets.
     *
     * @return bool
     */
    public function shouldIncludeGlobalOutlets(): bool
    {
        // Default is to be outlet-specific.
        // Can be overridden in models that allow global data.
        return false;
    }
}
