<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Gate;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Don't check for certain routes or if not logged in
        if (!auth()->check() || $request->is('api/v1/auth/*')) {
            return $next($request);
        }

        // Super admin bypasses subscription check
        if (auth()->user()->hasRole('super_admin')) {
            return $next($request);
        }

        if (Gate::denies('has-active-subscription')) {
            // Avoid blocking dashboard stats with 403 to prevent intrusive toast loops.
            // These will return data if it exists, but usually features are locked elsewhere.
            if ($request->is('api/v1/reports/daily') || $request->is('api/v1/reports/top-products')) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Anda tidak memiliki langganan aktif. Silakan berlangganan untuk mengakses fitur ini.',
            ], 403);
        }

        return $next($request);
    }
}
