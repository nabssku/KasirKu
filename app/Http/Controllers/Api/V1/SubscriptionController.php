<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Services\PlanLimitService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected PlanLimitService    $planLimit
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
                'subscription'  => $subscription,
                'tenant_name'   => $tenant->name,
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

        try {
            $paymentTx = $this->subscriptionService->createPaymentTransaction(
                $tenant,
                $plan,
                $validated['billing_cycle'] ?? 'monthly'
            );

            return response()->json([
                'success' => true,
                'message' => 'Transaksi pembayaran berhasil dibuat. Silakan selesaikan pembayaran.',
                'data'    => [
                    'payment_transaction' => $paymentTx,
                    'payment_url'         => $paymentTx->payment_url,
                    'invoice_id'          => $paymentTx->invoice_id,
                    'final_amount'        => $paymentTx->final_amount,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check payment status by invoice ID (used by frontend polling).
     */
    public function checkPayment(string $invoice): JsonResponse
    {
        $status = $this->subscriptionService->syncPaymentStatus($invoice);
        
        $paymentTx = PaymentTransaction::where('invoice_id', $invoice)->first();

        return response()->json([
            'success'      => $status !== 'not_found',
            'invoice_id'   => $invoice,
            'status'       => $status,
            'amount'       => $paymentTx?->amount,
            'final_amount' => $paymentTx?->final_amount,
            'paid_at'      => $paymentTx?->paid_at,
            'expires_at'   => $paymentTx?->created_at?->addHours(24),
        ]);
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
