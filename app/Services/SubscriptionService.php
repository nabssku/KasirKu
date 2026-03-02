<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function __construct(
        protected BayarGgService $bayarGg
    ) {}

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
        $redirectUrl   = 'https://jagokasir.store/subscription';
        $customerEmail = $tenant->email ?? auth()->user()?->email ?? '';

        $result = $this->bayarGg->createPayment([
            'amount'         => $amount,
            'description'    => 'Langganan Paket ' . $plan->name . ' (' . $billingCycle . ')',
            'customer_name'  => $tenant->name,
            'customer_email' => $customerEmail,
            'redirect_url'   => $redirectUrl,
            'callback_url'   => $callbackUrl,
            'payment_method' => 'gopay_qris',
        ]);

        $invoiceId   = $result['data']['invoice_id']   ?? null;
        $paymentUrl  = $result['data']['payment_url']  ?? null;
        $finalAmount = $result['data']['final_amount'] ?? $amount;

        if (!$result['success'] || !$invoiceId) {
            Log::error('BayarGg createPayment failed', ['order_id' => $orderId, 'result' => $result]);
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
            'gateway'          => 'bayargg',
            'gateway_order_id' => $orderId,
            'invoice_id'       => $invoiceId,
            'payment_url'      => $paymentUrl,
            'final_amount'     => $finalAmount,
            'status'           => 'pending',
        ]);
    }

    // ─── Payment Status Sync (no webhook needed) ──────────────────────────────

    /**
     * Check a single invoice against bayar.gg and update local DB.
     * Called by frontend polling AND by the sync command.
     * Returns the final local status string.
     */
    public function syncPaymentStatus(string $invoiceId): string
    {
        $paymentTx = PaymentTransaction::where('invoice_id', $invoiceId)->first();
        if (!$paymentTx) return 'not_found';

        // Already settled — no need to call API again
        if (in_array($paymentTx->status, ['paid', 'expired', 'failed'])) {
            return $paymentTx->status;
        }

        $result = $this->bayarGg->checkPayment($invoiceId);
        if (!($result['success'] ?? false)) {
            Log::warning('BayarGg syncPaymentStatus: API call failed', ['invoice_id' => $invoiceId]);
            return $paymentTx->status;
        }

        $remoteStatus = $result['status'] ?? 'pending';
        $newStatus    = match($remoteStatus) {
            'paid'      => 'paid',
            'expired'   => 'expired',
            'cancelled' => 'failed',
            default     => 'pending',
        };

        if ($newStatus !== $paymentTx->status) {
            $paymentTx->update([
                'status'  => $newStatus,
                'paid_at' => $newStatus === 'paid' ? now() : null,
                'gateway_payload' => $result,
            ]);

            if ($newStatus === 'paid') {
                $this->activateSubscription($paymentTx->tenant_id);
            }
        }

        return $newStatus;
    }

    /**
     * Pull all PAID invoices from bayar.gg via list-payments
     * and sync any that are still pending locally.
     * Run this on a schedule (e.g., every 2 minutes).
     */
    public function syncPendingPayments(): int
    {
        $synced = 0;

        // Get pending transactions from local DB
        $pending = PaymentTransaction::where('gateway', 'bayargg')
            ->where('status', 'pending')
            ->whereNotNull('invoice_id')
            ->get();

        if ($pending->isEmpty()) return 0;

        // Fetch paid list from bayar.gg to batch-match
        $listResult = $this->bayarGg->listPayments(['status' => 'paid', 'limit' => 100]);
        $paidInvoices = collect($listResult['data'] ?? [])
            ->pluck('invoice_id')
            ->filter()
            ->flip(); // use as hashmap for O(1) lookup

        foreach ($pending as $tx) {
            $invoiceId = $tx->invoice_id;

            if ($paidInvoices->has($invoiceId)) {
                // Batch-confirmed as paid — do a precise check to get full data
                $this->syncPaymentStatus($invoiceId);
                $synced++;
            } else {
                // Also individually check to catch expired/cancelled
                $checkResult = $this->bayarGg->checkPayment($invoiceId);
                $remoteStatus = $checkResult['status'] ?? 'pending';

                $newStatus = match($remoteStatus) {
                    'paid'      => 'paid',
                    'expired'   => 'expired',
                    'cancelled' => 'failed',
                    default     => 'pending',
                };

                if ($newStatus !== 'pending') {
                    $tx->update([
                        'status'  => $newStatus,
                        'paid_at' => $newStatus === 'paid' ? now() : null,
                        'gateway_payload' => $checkResult,
                    ]);

                    if ($newStatus === 'paid') {
                        $this->activateSubscription($tx->tenant_id);
                        $synced++;
                    }
                }
            }
        }

        return $synced;
    }

    /**
     * Activate the most recent pending subscription for a tenant.
     */
    public function activateSubscription(string $tenantId): void
    {
        $subscription = Subscription::where('tenant_id', $tenantId)
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

        Log::info('BayarGg: subscription activated', [
            'tenant_id'       => $tenantId,
            'subscription_id' => $subscription->id,
            'ends_at'         => $endsAt,
        ]);
    }

    // ─── Webhook (fallback, may not fire for gopay_qris) ─────────────────────

    /**
     * Handle bayar.gg payment webhook callback.
     *
     * Signature from headers:
     *   X-Webhook-Signature  — HMAC-SHA256 hex digest
     *   X-Webhook-Timestamp  — unix timestamp string
     *
     * Signed data: "{invoice_id}|{status}|{final_amount}|{timestamp}"
     */
    public function handleWebhook(array $payload, ?string $headerSignature = null, ?string $headerTimestamp = null): void
    {
        $invoiceId   = $payload['invoice_id']   ?? null;
        $status      = $payload['status']        ?? '';
        $finalAmount = (int) ($payload['final_amount'] ?? 0);

        if (!$invoiceId) {
            Log::warning('BayarGg webhook: missing invoice_id');
            return;
        }

        // Verify HMAC-SHA256 signature from headers
        if ($headerSignature && $headerTimestamp) {
            if (!$this->bayarGg->verifySignature($invoiceId, $status, $finalAmount, $headerTimestamp, $headerSignature)) {
                Log::warning('BayarGg webhook: invalid signature', ['invoice_id' => $invoiceId]);
                return;
            }
        }

        // Delegate to unified sync method
        $this->syncPaymentStatus($invoiceId);
    }
}
