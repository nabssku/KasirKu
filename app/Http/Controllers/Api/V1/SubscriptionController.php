<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PlanLimitService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected PlanLimitService $planLimit
    ) {}

    public function plans(): JsonResponse
    {
        $plans = Plan::with('features')->where('is_active', true)->orderBy('price')->get();

        return response()->json(['success' => true, 'data' => $plans]);
    }

    public function current(): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'subscription'  => null,
                    'tenant_name'   => null,
                    'tenant_status' => null,
                ],
            ]);
        }

        $subscription = $this->subscriptionService->getActive($tenant->id);

        if ($subscription) {
            $subscription->load('plan.features');
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'subscription' => $subscription,
                'tenant_name'  => $tenant->name,
                'tenant_status' => $tenant->status,
            ],
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id'       => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,yearly'],
        ]);

        $plan   = Plan::findOrFail($validated['plan_id']);
        $tenant = auth()->user()->tenant;

        $paymentTx = $this->subscriptionService->createPaymentTransaction(
            $tenant,
            $plan,
            $validated['billing_cycle'] ?? 'monthly'
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment transaction created. Proceed with Midtrans payment.',
            'data'    => [
                'payment_transaction' => $paymentTx,
                'snap_token'          => $paymentTx->snap_token,
                'client_key'          => config('services.midtrans.client_key'),
            ],
        ], 201);
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        $this->subscriptionService->handleWebhook($payload);

        return response()->json(['success' => true]);
    }

    public function history(): JsonResponse
    {
        $subscriptions = Subscription::where('tenant_id', auth()->user()->tenant_id)
            ->with('plan')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $subscriptions]);
    }

    /**
     * GET /subscription/usage
     * Returns how many of each resource the tenant has used vs. their plan limit.
     */
    public function usage(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $usage    = $this->planLimit->getUsage($tenantId);

        return response()->json(['success' => true, 'data' => $usage]);
    }
}
