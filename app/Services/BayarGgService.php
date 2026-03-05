<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BayarGgService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('services.bayargg.api_key');
        $this->baseUrl = rtrim(config('services.bayargg.base_url', 'https://bayar.gg/api'), '/');
    }

    /**
     * Build a pre-configured HTTP client with bayar.gg auth headers.
     */
    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'X-API-Key'    => $this->apiKey,
        ]);
    }

    /**
     * Create a new payment invoice on bayar.gg.
     *
     * @param  array{
     *   amount: int,
     *   description: string,
     *   customer_name: string,
     *   customer_email: string,
     *   redirect_url: string,
     *   callback_url: string,
     *   payment_method?: string
     * } $data
     */
    public function createPayment(array $data): array
    {
        $payload = array_merge([
            'payment_method' => 'gopay_qris',
        ], $data);

        try {
            $response = $this->client()->post("{$this->baseUrl}/create-payment.php", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('BayarGg createPayment error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return ['success' => false, 'message' => 'Failed to create payment: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('BayarGg createPayment exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check the payment status of a given invoice.
     */
    public function checkPayment(string $invoiceId): array
    {
        try {
            // Returning to /check-payment (no .php) as per user's manual curl example
            $response = $this->client()->get("{$this->baseUrl}/check-payment", [
                'invoice' => $invoiceId,
            ]);

            if ($response->successful()) {
                $json = $response->json();
                
                // Normalise: BayarGG usually wraps status inside 'data', but some versions might be flat
                $data = $json['data'] ?? $json;
                
                // Ensure success flag and top-level fields for easy access
                return array_merge($data, [
                    'success'    => $json['success'] ?? true,
                    'status'     => $data['status'] ?? 'pending',
                    'invoice_id' => $data['invoice_id'] ?? $invoiceId
                ]);
            }

            Log::error('BayarGg checkPayment error', [
                'status'     => $response->status(),
                'body'       => $response->body(),
                'invoice_id' => $invoiceId,
            ]);

            return ['success' => false, 'message' => 'Failed to check payment: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('BayarGg checkPayment exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * List payments from bayar.gg (for admin overview).
     *
     * @param array{status?: string, limit?: int, page?: int} $params
     */
    public function listPayments(array $params = []): array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/list-payments", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('BayarGg listPayments error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return ['success' => false, 'message' => 'Failed to list payments'];
        } catch (\Exception $e) {
            Log::error('BayarGg listPayments exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get payment statistics from bayar.gg (super admin only).
     *
     * @param string $period  day | week | month | year
     */
    public function getStatistics(string $period = 'month'): array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/get-statistics", [
                'period' => $period,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('BayarGg getStatistics error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return ['success' => false, 'message' => 'Failed to get statistics'];
        } catch (\Exception $e) {
            Log::error('BayarGg getStatistics exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify the HMAC-SHA256 signature from bayar.gg webhook headers.
     *
     * Headers:
     *   X-Webhook-Signature  — the HMAC-SHA256 hex digest
     *   X-Webhook-Timestamp  — unix timestamp string
     *
     * Data to sign: "{invoice_id}|{status}|{final_amount}|{timestamp}"
     */
    public function verifySignature(
        string $invoiceId,
        string $status,
        int    $finalAmount,
        string $timestamp,
        string $signature
    ): bool {
        $signatureData = $invoiceId . '|' . $status . '|' . $finalAmount . '|' . $timestamp;
        $expected      = hash_hmac('sha256', $signatureData, $this->apiKey);
        return hash_equals($expected, $signature);
    }
}
