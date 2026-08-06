<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Vercel / Cloudflare Reverse Proxies so HTTPS scheme is preserved
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified'     => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'tenant'       => \App\Http\Middleware\TenantMiddleware::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
            'role'         => \App\Http\Middleware\CheckRole::class,
            'permission'   => \App\Http\Middleware\CheckPermission::class,
            'plan.limit'   => \App\Http\Middleware\CheckPlanLimit::class,
            'feature'      => \App\Http\Middleware\CheckFeatureAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure API requests always return JSON instead of 302 redirects
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });
    })->create();

if (getenv('VERCEL') || isset($_SERVER['VERCEL']) || isset($_ENV['VERCEL'])) {
    $app->useStoragePath('/tmp');
}

return $app;
