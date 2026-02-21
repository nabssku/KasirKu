<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Returns the active subscription for a tenant (trial or paid).
     */
    public function getActive(string $tenantId): ?Subscription
    {
        return Subscription::with('plan')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['trial', 'active'])
            ->latest()
            ->first();
    }

    /**
     * Enroll a new tenant in the trial for a given plan.
     */
    public function startTrial(Tenant $tenant, ?int $planId = null): Subscription
    {
        $plan = $planId ? Plan::findOrFail($planId) : Plan::where('slug', 'starter')->first();
        $trialDays = $plan?->trial_days ?? 14;

        return Subscription::create([
            'tenant_id'     => $tenant->id,
            'plan_id'       => $plan?->id,
            'status'        => 'trial',
            'trial_ends_at' => Carbon::now()->addDays($trialDays),
            'starts_at'     => Carbon::now(),
            'ends_at'       => Carbon::now()->addDays($trialDays),
        ]);
    }

    /**
     * Check if the tenant's subscription is active (trial or paid).
     */
    public function isActive(Tenant $tenant): bool
    {
        return Subscription::where('tenant_id', $tenant->id)
            ->where(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>=', now())
                  ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('ends_at', '>=', now()));
            })
            ->exists();
    }

    /**
     * Check if a feature key is enabled on the tenant's current plan.
     */
    public function hasFeature(Tenant $tenant, string $featureKey): bool
    {
        $subscription = $this->getActive($tenant->id);
        if (!$subscription || !$subscription->plan) {
            return false;
        }

        return $subscription->plan->hasFeature($featureKey);
    }

    /**
     * Auto-suspend expired tenants (run via scheduled command).
     */
    public function suspendExpired(): int
    {
        return DB::transaction(function () {
            $expiredTenantIds = Subscription::where('status', 'active')
                ->where('ends_at', '<', now())
                ->pluck('tenant_id');

            Subscription::whereIn('tenant_id', $expiredTenantIds)->update(['status' => 'expired']);

            return Tenant::whereIn('id', $expiredTenantIds)->update(['status' => 'suspended']);
        });
    }

    /**
     * Create a Midtrans Snap payment transaction using REST API.
     */
    public function createPaymentTransaction(Tenant $tenant, Plan $plan, string $billingCycle = 'monthly'): PaymentTransaction
    {
        $orderId = 'SUB-' . strtoupper(substr($tenant->id, 0, 8)) . '-' . time();
        $amount  = (int) $plan->price;

        // Build Midtrans Snap API params
        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $tenant->name,
                'email'      => $tenant->email ?? auth()->user()->email ?? '',
            ],
            'item_details' => [
                [
                    'id'       => 'plan-' . $plan->id,
                    'price'    => $amount,
                    'quantity' => 1,
                    'name'     => 'Paket ' . $plan->name . ' (' . $billingCycle . ')',
                ],
            ],
        ];

        // Call Midtrans Snap API
        $snapToken = null;
        $serverKey = config('services.midtrans.server_key');
        $isProduction = config('services.midtrans.is_production');
        $baseUrl = $isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
                ->post($baseUrl, $params);

            if ($response->successful()) {
                $snapToken = $response->json('token');
            } else {
                Log::error('Midtrans Snap API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Midtrans Snap API exception', ['error' => $e->getMessage()]);
        }

        // Create pending subscription
        Subscription::create([
            'tenant_id'     => $tenant->id,
            'plan_id'       => $plan->id,
            'status'        => 'pending',
            'starts_at'     => null,
            'ends_at'       => null,
        ]);

        return PaymentTransaction::create([
            'tenant_id'        => $tenant->id,
            'type'             => 'subscription',
            'amount'           => $amount,
            'gateway'          => 'midtrans',
            'gateway_order_id' => $orderId,
            'snap_token'       => $snapToken,
            'status'           => 'pending',
        ]);
    }

    /**
     * Handle Midtrans payment notification (webhook).
     */
    public function handleWebhook(array $payload): void
    {
        $orderId     = $payload['order_id'] ?? null;
        $statusCode  = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $sigKey      = $payload['signature_key'] ?? '';

        if (!$orderId) return;

        // Verify Midtrans signature
        $serverKey = config('services.midtrans.server_key');
        $expectedSig = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if (!hash_equals($expectedSig, $sigKey)) {
            Log::warning('Midtrans webhook: Invalid signature', ['order_id' => $orderId]);
            return;
        }

        $paymentTx = PaymentTransaction::where('gateway_order_id', $orderId)->first();
        if (!$paymentTx) return;

        $txStatus = $payload['transaction_status'] ?? '';
        $fraudStatus = $payload['fraud_status'] ?? '';

        $newStatus = match(true) {
            in_array($txStatus, ['capture', 'settlement']) && $fraudStatus !== 'deny' => 'paid',
            $txStatus === 'expire' => 'expired',
            in_array($txStatus, ['cancel', 'deny']) => 'failed',
            default => 'pending',
        };

        $paymentTx->update([
            'status'                 => $newStatus,
            'gateway_transaction_id' => $payload['transaction_id'] ?? null,
            'gateway_payload'        => $payload,
            'paid_at'                => $newStatus === 'paid' ? now() : null,
        ]);

        // Activate subscription on successful payment
        if ($newStatus === 'paid') {
            $subscription = Subscription::where('tenant_id', $paymentTx->tenant_id)
                ->latest()
                ->first();

            if ($subscription) {
                $plan = Plan::find($subscription->plan_id);
                $now = now();
                $endsAt = $plan && $plan->billing_cycle === 'yearly'
                    ? $now->copy()->addYear()
                    : $now->copy()->addMonth();

                $subscription->update([
                    'status'    => 'active',
                    'starts_at' => $now,
                    'ends_at'   => $endsAt,
                ]);

                // Reactivate tenant if suspended
                $subscription->tenant?->update([
                    'status' => 'active',
                    'subscription_ends_at' => $endsAt,
                ]);
            }
        }
    }
}
