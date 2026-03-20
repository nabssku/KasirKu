<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSettingController extends Controller
{
    /**
     * Get global payment settings (SuperAdmin).
     */
    public function getGlobalSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'subscription_gateway' => SystemSetting::get('subscription_gateway', 'midtrans'),
                'midtrans_config'      => SystemSetting::get('midtrans_config', [
                    'client_key'    => '',
                    'server_key'    => '',
                    'is_production' => false,
                ]),
                'pakasir_config'       => SystemSetting::get('pakasir_config', [
                    'slug'       => '',
                    'api_key'    => '',
                    'is_sandbox' => true,
                ]),
                'trial_plan_id'        => SystemSetting::get('trial_plan_id', null),
            ],
        ]);
    }

    /**
     * Update global payment settings (SuperAdmin).
     */
    public function updateGlobalSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_gateway' => 'required|string|in:midtrans,pakasir',
            'midtrans_config'      => 'nullable|array',
            'pakasir_config'       => 'nullable|array',
            'trial_plan_id'        => 'nullable|integer|exists:plans,id',
        ]);

        SystemSetting::set('subscription_gateway', $validated['subscription_gateway']);
        
        if (isset($validated['midtrans_config'])) {
            SystemSetting::set('midtrans_config', $validated['midtrans_config']);
        }
        
        if (isset($validated['pakasir_config'])) {
            SystemSetting::set('pakasir_config', $validated['pakasir_config']);
        }

        if (array_key_exists('trial_plan_id', $validated)) {
            SystemSetting::set('trial_plan_id', $validated['trial_plan_id']);
        }

        return response()->json(['success' => true, 'message' => 'Global payment settings updated']);
    }

    /**
     * Get tenant payment settings (Owner).
     */
    public function getTenantSettings(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $settings = $tenant->settings ?? [];

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_gateway' => $settings['payment_gateway'] ?? 'midtrans',
                'midtrans_config' => $settings['midtrans_config'] ?? [
                    'client_key'    => '',
                    'server_key'    => '',
                    'is_production' => false,
                ],
                'pakasir_config'  => $settings['pakasir_config'] ?? [
                    'slug'       => '',
                    'api_key'    => '',
                    'is_sandbox' => true,
                ],
            ],
        ]);
    }

    /**
     * Update tenant payment settings (Owner).
     */
    public function updateTenantSettings(Request $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        $validated = $request->validate([
            'payment_gateway' => 'required|string|in:midtrans,pakasir',
            'midtrans_config' => 'nullable|array',
            'pakasir_config'  => 'nullable|array',
        ]);

        $settings = $tenant->settings ?? [];
        $settings['payment_gateway'] = $validated['payment_gateway'];
        
        if (isset($validated['midtrans_config'])) {
            $settings['midtrans_config'] = $validated['midtrans_config'];
        }
        
        if (isset($validated['pakasir_config'])) {
            $settings['pakasir_config'] = $validated['pakasir_config'];
        }

        $tenant->update(['settings' => $settings]);

        return response()->json(['success' => true, 'message' => 'Payment settings updated']);
    }
}
