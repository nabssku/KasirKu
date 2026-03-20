<?php

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    /**
     * Create a payment request.
     * 
     * @param array $params
     * @return array { success: bool, invoice_id: string, payment_url: string, snap_token?: string, final_amount?: int, raw: json }
     */
    public function createPayment(array $params): array;

    /**
     * Check payment status.
     * 
     * @param string $externalId Gateway's invoice/order ID
     * @param array $context Additional context for status check
     * @return array { success: bool, status: string, raw: json }
     */
    public function checkPayment(string $externalId, array $context = []): array;

    /**
     * Handle webhook/notification.
     * 
     * @param array $payload
     * @return array { success: bool, invoice_id: string, status: string, raw: json }
     */
    public function handleWebhook(array $payload): array;

    /**
     * Map gateway status to internal status (pending, paid, failed, expired).
     */
    public function mapStatus(string $status): string;
}
