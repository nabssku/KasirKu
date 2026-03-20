<?php

namespace App\Services\Payment;

use App\Models\Tenant;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class PaymentGatewayFactory
{
    /**
     * Get the gateway for SaaS subscriptions (global).
     */
    public static function getSubscriptionGateway(): PaymentGatewayInterface
    {
        $gatewayName = SystemSetting::get('subscription_gateway', 'midtrans');
        $config      = SystemSetting::get("{$gatewayName}_config", []);

        return self::make($gatewayName, $config);
    }

    /**
     * Get the gateway for a specific tenant (merchant).
     */
    public static function getTenantGateway(Tenant $tenant): PaymentGatewayInterface
    {
        $settings    = $tenant->settings ?? [];
        $gatewayName = $settings['payment_gateway'] ?? 'midtrans';
        $config      = $settings["{$gatewayName}_config"] ?? [];

        return self::make($gatewayName, $config);
    }

    /**
     * Resolve the service implementation.
     */
    protected static function make(string $gatewayName, array $config): PaymentGatewayInterface
    {
        return match ($gatewayName) {
            'midtrans' => new MidtransService($config),
            'pakasir'  => new PakasirService($config),
            default    => throw new \InvalidArgumentException("Unsupported payment gateway: {$gatewayName}"),
        };
    }
}
