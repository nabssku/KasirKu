<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            if ($user->hasRole('super_admin')) {
                // Super admin can optionally specify tenant scope via X-Tenant-Id header
                $tenantId = $request->header('X-Tenant-Id') ?? $user->tenant_id;
                if ($tenantId) {
                    app()->instance('current_tenant_id', $tenantId);
                }
            } else {
                // Non-super-admin users are strictly locked to their own tenant_id
                $tenantId = $user->tenant_id;
                if ($tenantId) {
                    app()->instance('current_tenant_id', $tenantId);
                }
            }
        }

        return $next($request);
    }
}
