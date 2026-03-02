<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Subscription;

class CheckFeatureAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        // Super admin bypasses all checks
        if (auth()->check() && auth()->user()->hasRole('super_admin')) {
            return $next($request);
        }

        $user = auth()->user();
        if (!$user || !$user->tenant_id) {
            return $next($request);
        }

        $subscription = Subscription::where('tenant_id', $user->tenant_id)
            ->where(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>=', now())
                  ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('ends_at', '>=', now()));
            })
            ->latest()
            ->first();

        if (!$subscription || !$subscription->plan) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki langganan aktif. Silakan berlangganan untuk mengakses fitur ini.',
            ], 403);
        }

        if (!$subscription->plan->hasFeature($featureKey)) {
            return response()->json([
                'success' => false,
                'message' => "Fitur ini tidak tersedia dalam paket {$subscription->plan->name}. Silakan upgrade paket Anda.",
                'feature_key' => $featureKey,
            ], 403);
        }

        return $next($request);
    }
}
