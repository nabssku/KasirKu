<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\SubscriptionService;
use App\Services\SelfOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected SelfOrderService $selfOrderService
    ) {}

    public function midtrans(Request $request): JsonResponse
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? '';

        Log::info('Midtrans Webhook Received', ['order_id' => $orderId]);

        $paymentTx = PaymentTransaction::where('gateway_order_id', $orderId)
            ->orWhere('invoice_id', $orderId)
            ->first();

        if (!$paymentTx) {
            Log::warning('Midtrans Webhook: Transaction not found', ['order_id' => $orderId]);
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        // Use appropriate service for status sync
        if ($paymentTx->type === 'subscription') {
            $this->subscriptionService->syncPaymentStatus($paymentTx->invoice_id);
        } else {
            $this->selfOrderService->syncPaymentStatus($paymentTx->invoice_id);
        }

        return response()->json(['success' => true]);
    }

    public function pakasir(Request $request): JsonResponse
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? '';

        Log::info('Pakasir Webhook Received', ['order_id' => $orderId]);

        $paymentTx = PaymentTransaction::where('gateway_order_id', $orderId)
            ->orWhere('invoice_id', $orderId)
            ->first();

        if (!$paymentTx) {
            Log::warning('Pakasir Webhook: Transaction not found', ['order_id' => $orderId]);
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        // Use appropriate service for status sync
        if ($paymentTx->type === 'subscription') {
            $this->subscriptionService->syncPaymentStatus($paymentTx->invoice_id);
        } else {
            $this->selfOrderService->syncPaymentStatus($paymentTx->invoice_id);
        }

        return response()->json(['success' => true]);
    }
}
