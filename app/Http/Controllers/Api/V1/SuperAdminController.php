<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    public function __construct() {}

    // ─── Dashboard Stats ──────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $totalTenants        = Tenant::withoutGlobalScopes()->count();
        $activeTenants       = Tenant::withoutGlobalScopes()->where('status', 'active')->count();
        $totalUsers          = User::withoutGlobalScopes()->whereNotNull('tenant_id')->count();
        $activeSubscriptions = Subscription::withoutGlobalScopes()->where('status', 'active')->count();
        $trialSubscriptions  = Subscription::withoutGlobalScopes()->where('status', 'trial')->count();
        
        // Actual paid revenue from payment transactions
        $totalPaidRevenue = PaymentTransaction::withoutGlobalScopes()
            ->where('status', 'paid')
            ->sum('amount');

        $totalOrders = PaymentTransaction::withoutGlobalScopes()->count();
        $pendingOrders = PaymentTransaction::withoutGlobalScopes()->where('status', 'pending')->count();

        $recentTenants = Tenant::withoutGlobalScopes()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_tenants'        => $totalTenants,
                'active_tenants'       => $activeTenants,
                'total_users'          => $totalUsers,
                'active_subscriptions' => $activeSubscriptions,
                'trial_subscriptions'  => $trialSubscriptions,
                'total_paid_revenue'   => (float) $totalPaidRevenue,
                'total_orders'         => $totalOrders,
                'pending_orders'       => $pendingOrders,
                'recent_tenants'       => $recentTenants,
            ],
        ]);
    }

    // ─── Tenants ──────────────────────────────────────────────────────────────

    public function tenants(Request $request): JsonResponse
    {
        $query = Tenant::withoutGlobalScopes()
            ->withCount('users')
            ->with('subscription.plan');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tenants = $query->orderByDesc('created_at')->paginate($request->input('per_page', 15));

        return response()->json(['success' => true, 'data' => $tenants]);
    }

    public function showTenant(string $id): JsonResponse
    {
        $tenant = Tenant::withoutGlobalScopes()
            ->withCount('users')
            ->with(['subscription.plan', 'users.roles'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $tenant]);
    }

    public function updateTenant(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'status'              => 'sometimes|in:active,inactive,suspended',
            'trial_ends_at'       => 'sometimes|nullable|date',
            'subscription_ends_at'=> 'sometimes|nullable|date',
        ]);

        $tenant->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tenant updated successfully.',
            'data'    => $tenant->fresh(),
        ]);
    }

    public function destroyTenant(string $id): JsonResponse
    {
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($id);
        $tenant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tenant deleted successfully.',
        ]);
    }

    // ─── Users (cross-tenant) ─────────────────────────────────────────────────

    public function users(Request $request): JsonResponse
    {
        $query = User::withoutGlobalScopes()
            ->with(['roles', 'tenant', 'outlet'])
            ->whereNotNull('tenant_id');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        if ($request->filled('role')) {
            $role = $request->input('role');
            $query->whereHas('roles', fn ($q) => $q->where('slug', $role));
        }

        $users = $query->orderByDesc('created_at')->paginate($request->input('per_page', 15));

        return response()->json(['success' => true, 'data' => $users]);
    }

    // ─── Subscriptions ────────────────────────────────────────────────────────

    public function subscriptions(Request $request): JsonResponse
    {
        $query = Subscription::withoutGlobalScopes()
            ->with(['tenant', 'plan']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('tenant', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $subscriptions = $query->orderByDesc('created_at')->paginate($request->input('per_page', 15));

        return response()->json(['success' => true, 'data' => $subscriptions]);
    }

    public function updateSubscription(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'plan_id'       => 'sometimes|integer|exists:plans,id',
            'status'        => 'sometimes|in:trial,active,expired,cancelled',
            'trial_ends_at' => 'sometimes|nullable|date',
            'starts_at'     => 'sometimes|nullable|date',
            'ends_at'       => 'sometimes|nullable|date',
        ]);

        $subscription->update($validated);

        // Sync tenant subscription_ends_at if ends_at changed
        if (isset($validated['ends_at'])) {
            $subscription->tenant()->update([
                'subscription_ends_at' => $validated['ends_at'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription updated successfully.',
            'data'    => $subscription->fresh()->load(['tenant', 'plan']),
        ]);
    }

    // ─── Plans CRUD ───────────────────────────────────────────────────────────

    public function plans(): JsonResponse
    {
        $plans = Plan::with('features')->orderBy('price')->get();

        return response()->json(['success' => true, 'data' => $plans]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'slug'             => 'required|string|max:255|unique:plans,slug',
            'price'            => 'required|numeric|min:0',
            'billing_cycle'    => 'required|in:monthly,yearly',
            'max_outlets'      => 'required|integer|min:1',
            'max_users'        => 'required|integer|min:1',
            'max_products'     => 'required|integer|min:1',
            'max_categories'   => 'sometimes|integer|min:1',
            'max_ingredients'  => 'sometimes|integer|min:1',
            'max_modifiers'    => 'sometimes|integer|min:1',
            'trial_days'       => 'sometimes|integer|min:0',
            'description'      => 'nullable|string',
            'is_active'        => 'boolean',
            'features'         => 'nullable|array',
            'features.*'       => 'string',
        ]);

        $features = $validated['features'] ?? [];
        unset($validated['features']);

        $plan = Plan::create($validated);

        foreach ($features as $key => $value) {
            PlanFeature::create([
                'plan_id'       => $plan->id,
                'feature_key'   => $key,
                'feature_value' => $value,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Plan created successfully.',
            'data'    => $plan->load('features'),
        ], 201);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'price'            => 'sometimes|numeric|min:0',
            'billing_cycle'    => 'sometimes|in:monthly,yearly',
            'max_outlets'      => 'sometimes|integer|min:1',
            'max_users'        => 'sometimes|integer|min:1',
            'max_products'     => 'sometimes|integer|min:1',
            'max_categories'   => 'sometimes|integer|min:1',
            'max_ingredients'  => 'sometimes|integer|min:1',
            'max_modifiers'    => 'sometimes|integer|min:1',
            'trial_days'       => 'sometimes|integer|min:0',
            'description'      => 'nullable|string',
            'is_active'        => 'boolean',
            'features'         => 'nullable|array',
        ]);

        $features = $validated['features'] ?? null;
        unset($validated['features']);

        $plan->update($validated);

        // Full sync: replace all features when provided
        if ($features !== null) {
            // Delete all existing features first
            PlanFeature::where('plan_id', $plan->id)->delete();

            // Re-create from the submitted list
            foreach ($features as $key => $value) {
                PlanFeature::create([
                    'plan_id'       => $plan->id,
                    'feature_key'   => $key,
                    'feature_value' => $value,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully.',
            'data'    => $plan->fresh()->load('features'),
        ]);
    }

    public function destroyPlan(int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        // Prevent deleting plans with active subscriptions
        if ($plan->subscriptions()->whereIn('status', ['active', 'trial'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete plan with active subscriptions.',
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Plan deleted successfully.',
        ]);
    }

    // ─── Orders / Revenue Tracking ────────────────────────────────────────────

    public function orders(Request $request): JsonResponse
    {
        $query = PaymentTransaction::withoutGlobalScopes()
            ->with('tenant');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('gateway_order_id', 'like', "%{$search}%")
                  ->orWhereHas('tenant', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->orderByDesc('created_at')->paginate($request->input('per_page', 15));

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function showOrder(string $id): JsonResponse
    {
        $order = PaymentTransaction::withoutGlobalScopes()
            ->with('tenant')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $order]);
    }

    /**
     * Get payment statistics for the platform.
     */
    public function paymentStatistics(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        
        $stats = PaymentTransaction::withoutGlobalScopes()
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }
}
