<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified'     => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'tenant'       => \App\Http\Middleware\TenantMiddleware::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
            'role'         => \App\Http\Middleware\CheckRole::class,
            'permission'   => \App\Http\Middleware\CheckPermission::class,
            'plan.limit'   => \App\Http\Middleware\CheckPlanLimit::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
