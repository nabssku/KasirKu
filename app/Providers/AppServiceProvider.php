<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\ProductRepositoryInterface::class,
            \App\Repositories\Eloquent\ProductRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\TransactionRepositoryInterface::class,
            \App\Repositories\Eloquent\TransactionRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\CustomerRepositoryInterface::class,
            \App\Repositories\Eloquent\CustomerRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\CategoryRepositoryInterface::class,
            \App\Repositories\Eloquent\CategoryRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Gates
        \Illuminate\Support\Facades\Gate::define('is-owner', function ($user) {
            return $user->hasRole('owner');
        });

        \Illuminate\Support\Facades\Gate::define('is-admin', function ($user) {
            return $user->hasRole('owner') || $user->hasRole('admin');
        });

        \Illuminate\Support\Facades\Gate::define('has-active-subscription', function ($user) {
            // Super admin bypasses subscription check
            if ($user->hasRole('super_admin')) {
                return true;
            }

            $tenant = \App\Models\Tenant::find($user->tenant_id);
            if (!$tenant) return false;
            
            return ($tenant->trial_ends_at && $tenant->trial_ends_at > now()) || 
                   ($tenant->subscription_ends_at && $tenant->subscription_ends_at > now());
        });

        \App\Models\Product::observe(\App\Observers\ProductObserver::class);

        // Events
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\TransactionCompleted::class,
            \App\Listeners\LogActivityListener::class
        );
    }
}
