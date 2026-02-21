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
        // Super admin operates without tenant scope
        if (auth()->check() && auth()->user()->hasRole('super_admin')) {
            return $next($request);
        }

        $tenantId = $request->header('X-Tenant-Id');

        if (!$tenantId && auth()->check()) {
            $tenantId = auth()->user()->tenant_id;
        }

        if ($tenantId) {
            app()->instance('current_tenant_id', $tenantId);
        }

        return $next($request);
    }
}
