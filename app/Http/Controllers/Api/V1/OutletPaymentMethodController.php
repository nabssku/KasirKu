<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutletPaymentMethodController extends Controller
{
    /**
     * Get available master payment methods and the outlet's configured payment methods.
     */
    public function index(string $outletId): JsonResponse
    {
        $outlet = Outlet::findOrFail($outletId);
        $tenantId = auth()->user()->tenant_id;
        
        // Fetch global master methods AND tenant-specific custom methods
        $masterMethods = PaymentMethod::withoutGlobalScopes()
            ->where(function($query) use ($tenantId) {
                $query->whereNull('tenant_id')
                      ->orWhere('tenant_id', $tenantId);
            })
            ->where('is_active', true)
            ->get();

        $outletMethods = $outlet->paymentMethods()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'master_methods' => $masterMethods,
                'outlet_methods' => $outletMethods,
            ],
        ]);
    }

    /**
     * Store a custom payment method for the tenant and enable it for the outlet.
     */
    public function storeCustom(Request $request, string $outletId): JsonResponse
    {
        $outlet = Outlet::findOrFail($outletId);
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'in:cash,e-wallet,card,bank_transfer,other'],
        ]);

        $paymentMethod = PaymentMethod::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'category' => $validated['category'],
            'code' => 'CUSTOM_' . strtoupper(str_replace(' ', '_', $validated['name'])) . '_' . uniqid(),
            'is_active' => true,
        ]);

        // Automatically enable for this outlet
        $outlet->paymentMethods()->attach($paymentMethod->id, [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'is_enabled' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom payment method created successfully.',
            'data' => $paymentMethod,
        ]);
    }

    /**
     * Update the outlet's payment methods.
     */
    public function update(Request $request, string $outletId): JsonResponse
    {
        $outlet = Outlet::findOrFail($outletId);

        $validated = $request->validate([
            'payment_methods' => ['required', 'array'],
            'payment_methods.*.payment_method_id' => ['required', 'uuid', 'exists:payment_methods,id'],
            'payment_methods.*.is_enabled' => ['required', 'boolean'],
            'payment_methods.*.config' => ['nullable', 'array'],
        ]);

        // Check subscription plan limit
        $user = $request->user();
        $tenant = $user->tenant;
        $subscription = $tenant->subscription; // Active or trial subscription
        $maxPaymentMethods = 2; // Default for Starter or if no subscription

        if ($subscription && $subscription->plan) {
            $featureValue = $subscription->plan->getFeatureValue('max_payment_methods');
            if ($featureValue !== null) {
                $maxPaymentMethods = (int) $featureValue;
            }
        }

        $enabledMethodsCount = collect($validated['payment_methods'])->where('is_enabled', true)->count();

        if ($enabledMethodsCount > $maxPaymentMethods) {
            return response()->json([
                'success' => false,
                'message' => "Your current plan only allows up to {$maxPaymentMethods} enabled payment methods. Please upgrade your plan for more.",
            ], 403);
        }

        $syncData = [];
        foreach ($validated['payment_methods'] as $method) {
            $syncData[$method['payment_method_id']] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'is_enabled' => $method['is_enabled'],
                'config' => isset($method['config']) ? json_encode($method['config']) : null,
            ];
        }

        $outlet->paymentMethods()->sync($syncData);

        return response()->json([
            'success' => true,
            'message' => 'Payment methods updated successfully.',
            'data' => $outlet->paymentMethods()->get(),
        ]);
    }
    
    /**
     * Get enabled payment methods for POS.
     */
    public function enabled(string $outletId): JsonResponse
    {
        $outlet = Outlet::findOrFail($outletId);
        
        $methods = $outlet->paymentMethods()
            ->wherePivot('is_enabled', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $methods,
        ]);
    }
}
