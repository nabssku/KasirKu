<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isOwner = $user->hasAnyRole(['owner', 'super_admin']);

        if (!$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only owners can access audit logs.'
            ], 403);
        }

        // Plan Check
        $tenant = $user->tenant;
        $activeSubscription = $tenant->subscriptions()
            ->where(function($query) {
                $query->where('status', 'active')
                    ->orWhere(function($q) {
                        $q->where('status', 'trial')->where('trial_ends_at', '>', now());
                    });
            })->first();

        $hasPlanAccess = false;
        if ($activeSubscription && $activeSubscription->plan) {
            $hasPlanAccess = $activeSubscription->plan->hasFeature('audit_log');
        }

        // If no plan access, we still return success:true but with a specific flag
        // so the frontend can show the CTA/Upgrade state
        if (!$hasPlanAccess) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'has_plan_access' => false,
                    'message' => 'Your current plan does not include Audit Logs. Please upgrade to access this feature.'
                ]
            ]);
        }

        $query = AuditLog::with(['user', 'outlet'])
            ->where('tenant_id', $user->tenant_id)
            ->latest();

        // Filtering
        if ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('model_type')) {
            $query->where('model_type', 'LIKE', '%' . $request->model_type . '%');
        }

        $logs = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'has_plan_access' => true
            ]
        ]);
    }
}
