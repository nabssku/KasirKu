<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PakasirService implements PaymentGatewayInterface
{
    protected string $slug;
    protected string $apiKey;
    protected bool $isSandbox;
    protected string $baseUrl;

    public function __construct(array $config)
    {
        $this->slug      = $config['slug'] ?? '';
        $this->apiKey    = $config['api_key'] ?? '';
        $this->isSandbox = $config['is_sandbox'] ?? false;
        $this->baseUrl   = 'https://app.pakasir.com/api';
    }

    public function createPayment(array $params): array
    {
        $method = $params['method'] ?? 'qris';
        $payload = [
            'project'  => $this->slug,
            'order_id' => $params['order_id'],
            'amount'   => (int) $params['amount'],
            'api_key'  => $this->apiKey,
        ];

        try {
            $url = "{$this->baseUrl}/transactioncreate/{$method}";
            $response = Http::post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $payment = $data['payment'] ?? [];
                $finalAmount = $payment['total_payment'] ?? (int)$params['amount'];
                $invoiceId   = $payment['order_id'] ?? $params['order_id'];
                
                // Construct payment URL manually from user instructions
                $redirectUrlBase = $params['redirect_url'] ?? 'https://jagokasir.store/dashboard';
                $paymentUrl = "https://app.pakasir.com/pay/{$this->slug}/{$finalAmount}?order_id={$invoiceId}&redirect={$redirectUrlBase}";

                return [
                    'success'      => true,
                    'invoice_id'   => $invoiceId,
                    'payment_url'  => $paymentUrl,
                    'payment_number' => $payment['payment_number'] ?? '',
                    'final_amount' => $finalAmount,
                    'raw'          => $data,
                ];
            }

            Log::error('Pakasir createPayment error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => 'Pakasir API Error: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Pakasir createPayment exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function checkPayment(string $externalId, array $context = []): array
    {
        $amount = $context['amount'] ?? 0;
        $params = [
            'project'  => $this->slug,
            'order_id' => $externalId,
            'amount'   => $amount,
            'api_key'  => $this->apiKey,
        ];

        try {
            $response = Http::get("{$this->baseUrl}/transactiondetail", $params);

            if ($response->successful()) {
                $data = $response->json();
                $tx = $data['transaction'] ?? [];
                return [
                    'success' => true,
                    'status'  => $this->mapStatus($tx['status'] ?? ''),
                    'raw'     => $data,
                ];
            }

            return ['success' => false, 'message' => 'Pakasir status check failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload): array
    {
        // Pakasir webhook format
        return [
            'success'    => true,
            'invoice_id' => $payload['order_id'] ?? '',
            'status'     => $this->mapStatus($payload['status'] ?? ''),
            'raw'        => $payload,
        ];
    }

    public function mapStatus(string $status): string
    {
        return match ($status) {
            'completed', 'success' => 'paid',
            'pending'             => 'pending',
            'cancelled', 'failed'  => 'failed',
            'expired'             => 'expired',
            default               => 'pending',
        };
    }
}
