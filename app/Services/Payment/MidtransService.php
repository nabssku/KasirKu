<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService implements PaymentGatewayInterface
{
    protected string $clientKey;
    protected string $serverKey;
    protected bool $isProduction;
    protected string $baseUrl;

    public function __construct(array $config)
    {
        $this->clientKey   = $config['client_key'] ?? '';
        $this->serverKey   = $config['server_key'] ?? '';
        $this->isProduction = $config['is_production'] ?? false;
        $this->baseUrl     = $this->isProduction 
            ? 'https://api.midtrans.com/v2' 
            : 'https://api.sandbox.midtrans.com/v2';
    }

    protected function getSnapUrl(): string
    {
        return $this->isProduction 
            ? 'https://app.midtrans.com/snap/v1/transactions' 
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    public function createPayment(array $params): array
    {
        $payload = [
            'transaction_details' => [
                'order_id'     => $params['order_id'],
                'gross_amount' => (int) $params['amount'],
            ],
            'customer_details' => [
                'first_name' => $params['customer_name'] ?? 'Customer',
                'email'      => $params['customer_email'] ?? '',
            ],
            'item_details' => [
                [
                    'id'       => $params['order_id'],
                    'price'    => (int) $params['amount'],
                    'quantity' => 1,
                    'name'     => $params['description'] ?? 'Transaction',
                ]
            ],
        ];

        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->post($this->getSnapUrl(), $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success'     => true,
                    'invoice_id'  => $params['order_id'],
                    'payment_url' => $data['redirect_url'],
                    'snap_token'  => $data['token'],
                    'raw'         => $data,
                ];
            }

            Log::error('Midtrans createPayment error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => 'Midtrans API Error: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Midtrans createPayment exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function checkPayment(string $externalId, array $context = []): array
    {
        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->get("{$this->baseUrl}/{$externalId}/status");

            if ($response->successful()) {
                $data   = $response->json();
                $status = $this->mapStatus($data['transaction_status'] ?? '');
                return [
                    'success' => true,
                    'status'  => $status,
                    'raw'     => $data,
                ];
            }

            return ['success' => false, 'message' => 'Midtrans status check failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload): array
    {
        // Midtrans webhook validation (checking signature)
        $orderId     = $payload['order_id'] ?? '';
        $statusCode  = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $signature   = $payload['signature_key'] ?? '';

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        if ($signature !== $expected) {
            Log::warning('Midtrans webhook invalid signature', ['payload' => $payload]);
            return ['success' => false, 'message' => 'Invalid signature'];
        }

        return [
            'success'    => true,
            'invoice_id' => $orderId,
            'status'     => $this->mapStatus($payload['transaction_status'] ?? ''),
            'raw'        => $payload,
        ];
    }

    public function mapStatus(string $status): string
    {
        return match ($status) {
            'capture', 'settlement' => 'paid',
            'pending'               => 'pending',
            'deny', 'cancel', 'expire' => 'failed',
            default                 => 'pending',
        };
    }
}
