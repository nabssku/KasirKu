<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CheckPlanLimit
{
    /**
     * Check if the tenant has reached the plan limit for a given resource.
     *
     * Usage in routes: middleware('plan.limit:products,max_products')
     *   - $resource     = table name (e.g. 'products')
     *   - $limitColumn  = column on plans table (e.g. 'max_products')
     *
     * Set plan column to -1 to allow unlimited creation.
     */
    public function handle(Request $request, Closure $next, string $resource, string $limitColumn): Response
    {
        // Super admin bypasses all limits
        if (auth()->check() && auth()->user()->hasRole('super_admin')) {
            return $next($request);
        }

        $user = auth()->user();
        if (!$user || !$user->tenant_id) {
            return $next($request);
        }

        $subscription = Subscription::with('plan')
            ->where('tenant_id', $user->tenant_id)
            ->where(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>=', now())
                  ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('ends_at', '>=', now()));
            })
            ->latest()
            ->first();

        if (!$subscription || !$subscription->plan) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki langganan aktif. Silakan berlangganan untuk melanjutkan.',
            ], 403);
        }

        $plan       = $subscription->plan;
        $maxAllowed = (int) ($plan->{$limitColumn} ?? 0);

        // -1 = unlimited, skip check
        if ($maxAllowed === -1) {
            return $next($request);
        }

        // Count non-deleted records for this tenant
        $currentCount = DB::table($resource)
            ->where('tenant_id', $user->tenant_id)
            ->whereNull('deleted_at')
            ->count();

        if ($currentCount >= $maxAllowed) {
            $resourceLabel = str_replace('_', ' ', $resource);
            return response()->json([
                'success'       => false,
                'message'       => "Batas {$resourceLabel} telah tercapai (maks: {$maxAllowed}). Silakan upgrade paket Anda.",
                'current_count' => $currentCount,
                'max_allowed'   => $maxAllowed,
            ], 403);
        }

        return $next($request);
    }
}
