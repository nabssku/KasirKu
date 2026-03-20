<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Services\Payment\PaymentGatewayFactory;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function __construct() {}

    // ─── Query Helpers ────────────────────────────────────────────────────────

    public function getActive(string $tenantId): ?Subscription
    {
        return Subscription::with('plan')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['trial', 'active'])
            ->latest()
            ->first();
    }

    public function startTrial(Tenant $tenant, ?int $planId = null): Subscription
    {
        $plan      = $planId ? Plan::findOrFail($planId) : Plan::where('slug', 'starter')->first();
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

    public function isActive(Tenant $tenant): bool
    {
        return Subscription::where('tenant_id', $tenant->id)
            ->where(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>=', now())
                  ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('ends_at', '>=', now()));
            })
            ->exists();
    }

    public function hasFeature(Tenant $tenant, string $featureKey): bool
    {
        $subscription = $this->getActive($tenant->id);
        if (!$subscription || !$subscription->plan) return false;
        return $subscription->plan->hasFeature($featureKey);
    }

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

    // ─── Payment Creation ─────────────────────────────────────────────────────

    public function createPaymentTransaction(Tenant $tenant, Plan $plan, string $billingCycle = 'monthly'): PaymentTransaction
    {
        $orderId       = 'SUB-' . strtoupper(substr($tenant->id, 0, 8)) . '-' . time();
        $amount        = (int) $plan->price;
        $callbackUrl   = config('app.url') . '/api/v1/subscriptions/webhook';
        $redirectUrl   = config('app.frontend_url') . '/subscription';
        $customerEmail = $tenant->email ?? auth()->user()?->email ?? '';

        $gateway = PaymentGatewayFactory::getSubscriptionGateway();

        $result = $gateway->createPayment([
            'amount'         => $amount,
            'description'    => 'Langganan Paket ' . $plan->name . ' (' . $billingCycle . ')',
            'order_id'       => $orderId,
            'customer_name'  => $tenant->name,
            'customer_email' => $customerEmail,
            'redirect_url'   => $redirectUrl,
            'callback_url'   => $callbackUrl,
        ]);

        $invoiceId   = $result['invoice_id']   ?? null;
        $paymentUrl  = $result['payment_url']  ?? null;
        $finalAmount = $result['final_amount'] ?? $amount;
        $gatewayName = SystemSetting::get('subscription_gateway', 'midtrans');

        if (!$result['success'] || !$invoiceId) {
            Log::error('Payment creation failed', ['order_id' => $orderId, 'result' => $result]);
            throw new \RuntimeException('Gagal membuat transaksi pembayaran.');
        }

        // Create a pending subscription record
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id'   => $plan->id,
            'status'    => 'pending',
            'starts_at' => null,
            'ends_at'   => null,
        ]);

        return PaymentTransaction::create([
            'tenant_id'        => $tenant->id,
            'type'             => 'subscription',
            'amount'           => $amount,
            'gateway'          => $gatewayName,
            'gateway_order_id' => $orderId,
            'invoice_id'       => $invoiceId,
            'payment_url'      => $paymentUrl,
            'final_amount'     => $finalAmount,
            'status'           => 'pending',
            'gateway_payload'  => $result['raw'] ?? $result,
        ]);
    }

    // ─── Payment Status Sync ──────────────────────────────────────────────────

    /**
     * Check a single payment status against the gateway and update local DB.
     */
    public function syncPaymentStatus(string $invoiceId): string
    {
        $paymentTx = PaymentTransaction::where('invoice_id', $invoiceId)->first();
        if (!$paymentTx) return 'not_found';

        if (in_array($paymentTx->status, ['paid', 'expired', 'failed'])) {
            return $paymentTx->status;
        }

        $gateway = PaymentGatewayFactory::getSubscriptionGateway();
        $result  = $gateway->checkPayment($invoiceId);

        if (!($result['success'] ?? false)) {
            Log::warning('Payment status check failed', ['invoice_id' => $invoiceId]);
            return $paymentTx->status;
        }

        $newStatus = $result['status'] ?? 'pending';

        if ($newStatus !== $paymentTx->status) {
            $paymentTx->update([
                'status'  => $newStatus,
                'paid_at' => $newStatus === 'paid' ? now() : null,
                'gateway_payload' => $result['raw'] ?? $result,
            ]);

            if ($newStatus === 'paid') {
                $this->activateSubscription($paymentTx->tenant_id);
            }
        }

        return $newStatus;
    }

    /**
     * Sync all pending subscription payments.
     */
    public function syncPendingPayments(): int
    {
        $pending = PaymentTransaction::where('type', 'subscription')
            ->where('status', 'pending')
            ->whereNotNull('invoice_id')
            ->get();

        $count = 0;
        foreach ($pending as $tx) {
            $this->syncPaymentStatus($tx->invoice_id);
            $count++;
        }

        return $count;
    }

    /**
     * Activate the most recent pending subscription for a tenant.
     */
    public function activateSubscription(string $tenantId): void
    {
        $subscription = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (!$subscription) return;

        $plan = Plan::find($subscription->plan_id);
        $now  = now();
        $endsAt = $plan && $plan->billing_cycle === 'yearly'
            ? $now->copy()->addYear()
            : $now->copy()->addMonth();

        $subscription->update([
            'status'    => 'active',
            'starts_at' => $now,
            'ends_at'   => $endsAt,
        ]);

        Tenant::where('id', $tenantId)->update([
            'status'               => 'active',
            'subscription_ends_at' => $endsAt,
        ]);

        Log::info('Subscription activated', [
            'tenant_id'       => $tenantId,
            'subscription_id' => $subscription->id,
            'ends_at'         => $endsAt,
        ]);
    }
}
