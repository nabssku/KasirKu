<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Services\OutletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OutletController extends Controller
{
    public function __construct(protected OutletService $outletService) {}

    public function index(Request $request): JsonResponse
    {
        $outlets = $this->outletService->all((int) $request->query('per_page', 15));

        return response()->json(['success' => true, 'data' => $outlets]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'business_type'  => ['nullable', 'string', 'in:fnb,retail'],
            'address'        => ['nullable', 'string'],
            'phone'          => ['nullable', 'string'],
            'email'          => ['nullable', 'email'],
            'tax_rate'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_charge' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $outlet = $this->outletService->create($validated);

        return response()->json(['success' => true, 'data' => $outlet], 201);
    }

    public function show(string $id): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);

        return response()->json(['success' => true, 'data' => $outlet]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);

        // Decode receipt_settings if sent as a string (FormData)
        if (is_string($request->input('receipt_settings'))) {
            $request->merge([
                'receipt_settings' => json_decode($request->input('receipt_settings'), true)
            ]);
        }

        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'business_type'  => ['nullable', 'string', 'in:fnb,retail'],
            'address'        => ['nullable', 'string'],
            'phone'          => ['nullable', 'string'],
            'email'          => ['nullable', 'email'],
            'tax_rate'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_charge' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active'      => ['nullable', 'boolean'],
            'receipt_settings' => ['nullable', 'array'],
            'google_review_link' => ['nullable', 'string'],
            'logo'           => ['nullable', 'image', 'max:2048'],
        ]);
        
        $settings = $validated['receipt_settings'] ?? $outlet->receipt_settings ?? [];
        
        // --- Feature Check: Receipt Customization (Pro) ---
        $user = auth()->user();
        $subscription = Subscription::with('plan.features')
            ->where('tenant_id', $user->tenant_id)
            ->where(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>=', now())
                  ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('ends_at', '>=', now()));
            })
            ->latest()
            ->first();

        $hasWhiteLabel = $subscription?->plan?->hasFeature('white_label') ?? false;

        if (!$hasWhiteLabel) {
            // Discard logo if sent
            unset($validated['logo']);
            
            // Revert/Reset Pro settings to default if they were attempted to be changed
            // We only allow store_name, header_text, and footer_text for basic plans
            $settings['font_size']   = 'medium';
            $settings['alignment']   = 'center';
            $settings['paper_width'] = 32;
            $settings['logo_width']  = 80;
            
            // Ensure no logo_url remains if not pro (optional: might want to keep existing if they once had pro, 
            // but usually we hide it)
            unset($settings['logo_url']);
        }

        // Handle logo upload (only if pro)
        if ($hasWhiteLabel && $request->hasFile('logo')) {
            // Delete old logo if exists
            if (isset($outlet->receipt_settings['logo_url'])) {
                $oldPath = str_replace(Storage::disk('public')->url(''), '', $outlet->receipt_settings['logo_url']);
                Storage::disk('public')->delete($oldPath);
            }
            
            $path = $request->file('logo')->store('logos', 'public');
            $settings['logo_url'] = Storage::disk('public')->url($path);
        }

        // Merge the potentially updated settings back into the validated data
        $validated['receipt_settings'] = $settings;

        // Update the outlet using the service
        $outlet = $this->outletService->update($outlet, $validated);

        return response()->json(['success' => true, 'data' => $outlet]);
    }

    public function destroy(string $id): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);
        $this->outletService->delete($outlet);

        return response()->json(['success' => true, 'message' => 'Outlet deleted.']);
    }
}
