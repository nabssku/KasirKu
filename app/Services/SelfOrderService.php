<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SelfOrderSession;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SelfOrderService
{
    public function __construct(
        protected KitchenOrderService $kitchenOrderService,
        protected ShiftService        $shiftService,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Validate QR and return outlet + table info for the Web Menu
    // ──────────────────────────────────────────────────────────────────────────
    public function resolveQrToken(string $qrToken): array
    {
        $table = RestaurantTable::with('outlet.tenant')
            ->where('qr_token', $qrToken)
            ->where('qr_enabled', true)
            ->first();

        if (!$table) {
            throw ValidationException::withMessages([
                'qr_token' => 'QR code tidak valid atau tidak aktif.',
            ]);
        }

        $outlet = $table->outlet;

        if (!$outlet || !$outlet->is_active) {
            throw ValidationException::withMessages([
                'outlet' => 'Outlet tidak aktif.',
            ]);
        }

        // Check tenant subscription allows self-order
        $this->assertTenantHasFeature($outlet->tenant_id, 'qr_self_order');

        return [
            'table'  => $table,
            'outlet' => $outlet,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Get public menu for an outlet
    // ──────────────────────────────────────────────────────────────────────────
    public function getPublicMenu(string $outletId): array
    {
        $products = Product::withoutGlobalScopes()
            ->with(['category', 'modifierGroups.modifiers'])
            ->where('outlet_id', $outletId)
            ->where('is_active', true)
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        $grouped = $products->groupBy(fn ($p) => $p->category?->name ?? 'Lainnya')
            ->map(fn ($items, $category) => [
                'category' => $category,
                'items'    => $items->values(),
            ])
            ->values();

        return $grouped->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Create a new cart session (30 min TTL)
    // ──────────────────────────────────────────────────────────────────────────
    public function createSession(RestaurantTable $table, Request $request): SelfOrderSession
    {
        // Expire any old active sessions for this table
        SelfOrderSession::where('table_id', $table->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        return SelfOrderSession::create([
            'tenant_id'     => $table->tenant_id,
            'outlet_id'     => $table->outlet_id,
            'table_id'      => $table->id,
            'session_token' => bin2hex(random_bytes(32)),
            'status'        => 'active',
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
            'expires_at'    => now()->addMinutes(30),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Submit the order: validate session → create Transaction (pending_payment)
    //    → trigger payment gateway → store PaymentTransaction
    // ──────────────────────────────────────────────────────────────────────────
    public function submitOrder(string $sessionToken, array $data, Request $request): array
    {
        return DB::transaction(function () use ($sessionToken, $data, $request) {

            // ── a. Validate session ──────────────────────────────────────────
            $session = SelfOrderSession::where('session_token', $sessionToken)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$session || $session->isExpired()) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi habis atau tidak valid. Silakan scan QR ulang.',
                ]);
            }

            $table  = $session->table;
            $outlet = $session->outlet;

            // ── b. Validate & price items from DB (never trust client prices) ─
            [$items, $subtotal] = $this->validateAndPriceItems($data['items'], $outlet->tenant_id);

            $taxRate       = $outlet->tax_rate ?? 0;
            $scRate        = $outlet->service_charge ?? 0;
            $tax           = $subtotal * ($taxRate / 100);
            $serviceCharge = $subtotal * ($scRate / 100);
            $grandTotal    = $subtotal + $tax + $serviceCharge;

            // ── c. Find any open shift for this outlet ───────────────────────
            $shift = Shift::where('outlet_id', $outlet->id)
                ->where('status', 'open')
                ->first();

            // ── d. Create Transaction in pending_payment status ──────────────
            $invoiceNumber = $this->generateInvoiceNumber($outlet->id);
            $paymentExpiry = now()->addMinutes(15);

            $transaction = Transaction::create([
                'tenant_id'          => $outlet->tenant_id,
                'outlet_id'          => $outlet->id,
                'user_id'            => null,                  // no cashier
                'table_id'           => $table->id,
                'shift_id'           => $shift?->id,
                'invoice_number'     => $invoiceNumber,
                'type'               => 'dine_in',
                'source'             => 'self_order',
                'customer_name'      => $data['customer_name'] ?? 'Tamu',
                'subtotal'           => $subtotal,
                'tax_rate'           => $taxRate,
                'tax'                => $tax,
                'service_charge'     => $serviceCharge,
                'discount'           => 0,
                'grand_total'        => $grandTotal,
                'paid_amount'        => 0,
                'change_amount'      => 0,
                'status'             => 'pending_payment',
                'notes'              => $data['notes'] ?? null,
                'payment_expires_at' => $paymentExpiry,
            ]);

            // ── e. Create TransactionItems ───────────────────────────────────
            foreach ($items as $item) {
                $modifiers = $item['modifiers'] ?? [];
                unset($item['modifiers']);
                $item['transaction_id'] = $transaction->id;

                $txItem = TransactionItem::create($item);

                foreach ($modifiers as $mod) {
                    if (method_exists($txItem, 'modifiers')) {
                        $txItem->modifiers()->create([
                            'modifier_id'   => $mod['modifier_id'],
                            'modifier_name' => $mod['modifier_name'] ?? $mod['name'],
                            'price'         => $mod['price'] ?? 0,
                        ]);
                    }
                }
            }

            // ── f. Mark session as submitted ─────────────────────────────────
            $session->update([
                'status'       => 'submitted',
                'cart_data'    => $data['items'],
                'submitted_at' => now(),
            ]);

            // ── g. Create payment via Gateway ───────────────────────────────
            $redirectUrl  = $data['redirect_url'] ?? url("/menu/order/{$session->session_token}/status");
            $customerName = $data['customer_name'] ?? 'Tamu';

            $gateway = PaymentGatewayFactory::getTenantGateway($outlet->tenant);

            $paymentResponse = $gateway->createPayment([
                'amount'         => (int) $grandTotal,
                'description'    => "Self Order #{$invoiceNumber} - Meja {$table->name}",
                'order_id'       => $invoiceNumber,
                'customer_name'  => $customerName,
                'customer_email' => 'tamu@self-order.local',
                'redirect_url'   => $redirectUrl,
                'method'         => 'qris',
            ]);

            $invoiceId   = $paymentResponse['invoice_id']  ?? null;
            $paymentUrl  = $paymentResponse['payment_url'] ?? null;
            $final_amount = $paymentResponse['final_amount'] ?? $grandTotal;
            $gatewayName = $outlet->tenant->settings['payment_gateway'] ?? 'midtrans';

            // ── g2. Generate Dynamic QRIS (Static to Dynamic) ────────────────
            $baseQris = "00020101021126610014COM.GO-JEK.WWW01189360091439981678490210G9981678490303UMI51440014ID.CO.QRIS.WWW0215ID10264896514830303UMI5204899953033605802ID5924jagokasir.store, Digital6006MALANG61056512362070703A0163040E80";
            if ($final_amount && isset($paymentResponse['data'])) {
                $convertedQris = $this->generateDynamicQris($baseQris, (int)$final_amount);
                $paymentResponse['data']['qris_converter'] = [
                    'original_qris'  => $baseQris,
                    'converted_qris' => $convertedQris
                ];
            }

            if (!$invoiceId) {
                // Roll back — transaction will not be kept (DB::transaction handles this)
                throw new \RuntimeException(
                    'Gagal membuat pembayaran QRIS: ' . ($paymentResponse['message'] ?? $paymentResponse['error'] ?? 'Unknown error')
                );
            }

            // ── h. Create PaymentTransaction record ───────────────────────────
            $paymentTx = PaymentTransaction::create([
                'tenant_id'      => $outlet->tenant_id,
                'transaction_id' => $transaction->id,
                'outlet_id'      => $outlet->id,
                'type'           => 'self_order_payment',
                'amount'         => $grandTotal,
                'gateway'        => $gatewayName,
                'gateway_order_id' => $invoiceNumber,
                'invoice_id'     => $invoiceId,
                'payment_url'    => $paymentUrl,
                'final_amount'   => (int) $final_amount,
                'status'         => 'pending',
                'expires_at'     => $paymentExpiry,
                'gateway_payload' => $paymentResponse['raw'] ?? $paymentResponse,
            ]);

            return [
                'transaction'   => $transaction,
                'paymentResponse' => $paymentResponse,
                'payment_url'   => $paymentUrl,
                'invoice_id'    => $invoiceId,
                'expires_at'    => $paymentExpiry->toIso8601String(),
                'session_token' => $sessionToken,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. Sync payment status manually
    // ──────────────────────────────────────────────────────────────────────────
    public function syncPaymentStatus(string $invoiceId): string
    {
        // Use withoutGlobalScopes as this might be called from a public context
        $paymentTx = PaymentTransaction::withoutGlobalScopes()
            ->where('invoice_id', $invoiceId)
            ->first();

        if (!$paymentTx) return 'not_found';

        // Check API regardless of local status to handle desyncs
        // between PaymentTransaction and Transaction tables.


        $gateway = PaymentGatewayFactory::getTenantGateway($paymentTx->tenant);
        $result  = $gateway->checkPayment($invoiceId, ['amount' => $paymentTx->amount]);

        if (!($result['success'] ?? false)) {
            Log::warning('SelfOrder payment check failed', [
                'invoice_id' => $invoiceId,
                'response'   => $result,
            ]);
            return $paymentTx->status;
        }

        $newStatus = $result['status'] ?? 'pending';

        Log::info('SelfOrder payment check', [
            'invoice_id'    => $invoiceId,
            'remote_status' => $remoteStatus,
            'new_status'    => $newStatus,
            'local_status'  => $paymentTx->status,
        ]);

        // Even if local paymentTx is already fixed, we should ensure the transaction is synced
        // if the remote status is paid.
        if ($newStatus !== $paymentTx->status || ($newStatus === 'paid' && $paymentTx->status === 'paid')) {
            if ($newStatus !== $paymentTx->status) {
                $paymentTx->update([
                    'status'          => $newStatus,
                    'gateway_payload' => $result['raw'] ?? $result,
                    'paid_at'         => $newStatus === 'paid' ? now() : null,
                ]);
            }

            $transaction = Transaction::withoutGlobalScopes()
                ->with(['items.modifiers', 'table'])
                ->find($paymentTx->transaction_id);

            if ($transaction) {
                if ($newStatus === 'paid' && $transaction->status === 'pending_payment') {
                    $this->confirmPayment($transaction, $paymentTx);
                } elseif (in_array($newStatus, ['failed', 'expired']) && $transaction->status === 'pending_payment') {
                    $this->cancelTransaction($transaction);
                }
            }
        }

        return $newStatus;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. Get order status for polling (public endpoint)
    // ──────────────────────────────────────────────────────────────────────────
    public function getOrderStatus(string $sessionToken): array
    {
        $session = SelfOrderSession::where('session_token', $sessionToken)->first();

        if (!$session) {
            throw ValidationException::withMessages(['session' => 'Sesi tidak ditemukan.']);
        }

        // Use withoutGlobalScopes to avoid outlet/tenant scope interference on public endpoint
        $transaction = Transaction::withoutGlobalScopes()
            ->with(['kitchenOrder'])
            ->where('outlet_id', $session->outlet_id)
            ->where('table_id', $session->table_id)
            ->where('source', 'self_order')
            ->whereIn('status', ['pending_payment', 'paid', 'preparing', 'ready', 'completed'])
            ->latest()
            ->first();

        $paymentChecking = false;
        $activePayment   = null;

        if ($transaction) {
            // 1. Always look for the payment record if transaction exists
            $activePayment = PaymentTransaction::withoutGlobalScopes()
                ->where('transaction_id', $transaction->id)
                ->where('type', 'self_order_payment')
                ->latest()
                ->first();

            // Fallback for old records
            if (!$activePayment) {
                Log::debug('SelfOrder: activePayment not found by transaction_id, trying fallback', [
                    'session_token' => $sessionToken,
                    'outlet_id'     => $session->outlet_id,
                ]);
                $activePayment = PaymentTransaction::withoutGlobalScopes()
                    ->where('type', 'self_order_payment')
                    ->where('outlet_id', $session->outlet_id)
                    ->whereNotNull('invoice_id')
                    ->where('created_at', '>=', $transaction->created_at->subMinutes(30))
                    ->latest()
                    ->first();
                
                if ($activePayment && !$activePayment->transaction_id) {
                    $activePayment->update(['transaction_id' => $transaction->id]);
                }
            }

            Log::debug('SelfOrder: getOrderStatus check', [
                'transaction_id'   => $transaction->id,
                'status'           => $transaction->status,
                'active_payment'   => $activePayment ? $activePayment->id : null,
                'payment_status'   => $activePayment ? $activePayment->status : null,
                'payment_invoice'  => $activePayment ? $activePayment->invoice_id : null,
            ]);

            // 2. Trigger sync if transaction is still pending_payment
            // We do this even if $activePayment->status is already 'paid' to handle desyncs
            if ($transaction->status === 'pending_payment' && $activePayment && !in_array($activePayment->status, ['failed', 'expired'])) {
                $paymentChecking = true;
                $newStatus = $this->syncPaymentStatus($activePayment->invoice_id);
                
                if ($newStatus === 'paid') {
                    $transaction->refresh();
                    $transaction->load('kitchenOrder');
                    $activePayment->refresh();
                }
            }
        }

        if (!$transaction) {
            return [
                'session_status'   => $session->status,
                'order_status'     => null,
                'kitchen_status'   => null,
                'payment_checking' => false,
                'message'          => 'Menunggu pesanan disubmit...',
            ];
        }

        // Determine effective status for message
        $effectiveStatus = $transaction->status;
        if ($transaction->status === 'paid' && $transaction->kitchenOrder) {
            if ($transaction->kitchenOrder->status === 'cooking') {
                $effectiveStatus = 'preparing';
            } elseif ($transaction->kitchenOrder->status === 'ready') {
                $effectiveStatus = 'ready';
            }
        }

        return [
            'session_status'     => $session->status,
            'order_status'       => $transaction->status,
            'kitchen_status'     => $transaction->kitchenOrder?->status,
            'invoice_number'     => $transaction->invoice_number,
            'gateway_invoice_id' => $activePayment?->invoice_id,
            'grand_total'        => $transaction->grand_total,
            'final_amount'       => $activePayment?->final_amount ?? (int) $transaction->grand_total,
            'payment_url'        => $activePayment?->payment_url,
            'payment_checking'   => $paymentChecking,
            'message'            => $this->statusMessage($effectiveStatus),
            'items'              => $transaction->items()->with('product:id,name,image')->get()->map(function($item) {
                return [
                    'id'            => $item->id,
                    'product_name'  => $item->product?->name ?? $item->product_name,
                    'image'         => $item->product?->image ? Storage::disk('public')->url($item->product->image) : null,
                    'quantity'      => $item->quantity,
                    'price'         => $item->price,
                ];
            }),
        ];
    }

    /**
     * Batch check all pending self-order payments.
     * Can be called by a scheduled command.
     */
    public function syncPendingPayments(): int
    {
        $pending = PaymentTransaction::where('type', 'self_order_payment')
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

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function confirmPayment(Transaction $transaction, PaymentTransaction $paymentTx): void
    {
        DB::transaction(function () use ($transaction, $paymentTx) {
            // Find active shift for this outlet
            $activeShift = $this->shiftService->getCurrentShift($transaction->outlet_id);

            // Update transaction status and link to shift
            $transaction->update([
                'status'      => 'paid',
                'paid_amount' => $transaction->grand_total,
                'shift_id'    => $activeShift?->id,
            ]);

            // Create Payment record
            Payment::create([
                'tenant_id'        => $transaction->tenant_id,
                'transaction_id'   => $transaction->id,
                'payment_method'   => 'e-wallet',
                'amount'           => $transaction->grand_total,
                'payment_reference' => $paymentTx->invoice_id,
                'paid_at'          => now(),
            ]);

            // Update table status to occupied
            if ($transaction->table_id) {
                RestaurantTable::where('id', $transaction->table_id)
                    ->update(['status' => 'occupied']);
            }

            // Create kitchen order
            $transaction->load('items');
            $this->kitchenOrderService->createFromTransaction($transaction);

            Log::info('SelfOrder payment confirmed', [
                'transaction_id' => $transaction->id,
                'invoice_id'     => $paymentTx->invoice_id,
            ]);
        });
    }

    private function cancelTransaction(Transaction $transaction): void
    {
        $transaction->update([
            'status'        => 'cancelled',
            'cancel_reason' => 'Pembayaran expired/gagal',
            'cancelled_at'  => now(),
        ]);
    }

    private function validateAndPriceItems(array $rawItems, string $tenantId): array
    {
        $items    = [];
        $subtotal = 0;

        foreach ($rawItems as $raw) {
            $product = Product::where('id', $raw['product_id'])
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->firstOrFail();

            // We allow ordering even if stock is 0 (negative stock) as many outlets 
            // don't track exact stock for food/beverages.
            // If strict stock management is needed, this can be gated by a 'track_stock' flag in the future.

            $qty          = max(1, (int) $raw['quantity']);
            $itemSubtotal = $product->price * $qty;
            $subtotal    += $itemSubtotal;

            $items[] = [
                'id'           => Str::uuid(),
                'tenant_id'    => $tenantId,
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'price'        => $product->price,
                'quantity'     => $qty,
                'subtotal'     => $itemSubtotal,
                'modifiers'    => $raw['modifiers'] ?? [],
            ];
        }

        return [$items, $subtotal];
    }

    private function generateInvoiceNumber(string $outletId): string
    {
        $prefix = 'SO-' . now()->format('ymd');
        $count  = Transaction::where('outlet_id', $outletId)
            ->where('source', 'self_order')
            ->whereDate('created_at', today())
            ->count();

        return $prefix . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    private function mapGatewayStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'PAID'              => 'paid',
            'EXPIRED'           => 'expired',
            'FAILED', 'CANCEL',
            'CANCELLED'         => 'failed',
            default             => 'pending',
        };
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'pending_payment' => 'Menunggu pembayaran...',
            'paid'            => 'Pembayaran berhasil! Pesanan sedang diproses.',
            'preparing'       => 'Dapur sedang menyiapkan pesanan Anda.',
            'ready'           => 'Pesanan Anda siap! Mohon ditunggu.',
            'completed'       => 'Pesanan selesai. Terima kasih!',
            'cancelled'       => 'Pesanan dibatalkan.',
            default           => 'Memproses...',
        };
    }

    private function assertTenantHasFeature(string $tenantId, string $featureKey): void
    {
        $subscription = Subscription::with('plan.features')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>=', now())
                  ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('ends_at', '>=', now()));
            })
            ->latest()
            ->first();

        if (!$subscription || !$subscription->plan) {
            throw ValidationException::withMessages([
                'plan' => 'Outlet tidak memiliki langganan aktif.',
            ]);
        }

        if (!$subscription->plan->hasFeature($featureKey)) {
            throw ValidationException::withMessages([
                'plan' => 'Fitur QR Self Order tidak tersedia pada paket ini. Silakan upgrade.',
            ]);
        }
    }

    /**
     * Convert static QRIS to Dynamic QRIS by embedding amount and recalculating CRC.
     */
    private function generateDynamicQris(string $staticQris, $amount): string
    {
        // 1. Remove last 4 chars (CRC)
        $qris = substr(trim($staticQris), 0, -4);

        // 2. Change static indicator (010211) to dynamic (010212)
        $qris = str_replace("010211", "010212", $qris);

        // 3. Prepare amount tag (54)
        $amountStr = (string)$amount;
        $amountTag = "54" . sprintf("%02d", strlen($amountStr)) . $amountStr;

        // 4. Insert amount before country code (5802ID)
        if (strpos($qris, "5802ID") !== false) {
            $parts = explode("5802ID", $qris);
            $qris = $parts[0] . $amountTag . "5802ID" . $parts[1];
        } else {
            // Fallback: append if tag not found
            $qris .= $amountTag;
        }

        // 5. Append new CRC
        return $qris . $this->crc16($qris);
    }

    /**
     * CCITT-FALSE CRC16 implementation
     */
    private function crc16(string $str): string
    {
        $crc = 0xFFFF;
        $strlen = strlen($str);
        for ($c = 0; $c < $strlen; $c++) {
            $crc ^= ord($str[$c]) << 8;
            for ($i = 0; $i < 8; $i++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc = $crc << 1;
                }
            }
        }
        $hex = strtoupper(dechex($crc & 0xFFFF));
        return str_pad($hex, 4, "0", STR_PAD_LEFT);
    }
}
