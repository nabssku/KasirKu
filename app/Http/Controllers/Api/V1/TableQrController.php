<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TableQrController extends Controller
{
    // ── POST /api/v1/tables/{id}/qr/generate ─────────────────────────────────
    // Generate or regenerate a QR token for a table
    public function generate(string $id): JsonResponse
    {
        $user  = auth()->user();
        $table = RestaurantTable::findOrFail($id);

        // Feature gate
        $this->assertFeature($user->tenant_id, 'qr_self_order');

        // Max QR tables check
        $maxQr  = (int) $this->getFeatureValue($user->tenant_id, 'max_qr_tables');
        if ($maxQr > 0) {
            $current = RestaurantTable::where('tenant_id', $user->tenant_id)
                ->where('qr_enabled', true)
                ->where('id', '!=', $table->id)
                ->count();

            if ($current >= $maxQr && !$table->qr_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => "Batas maksimum meja QR ({$maxQr}) telah tercapai.",
                ], 422);
            }
        }

        $token = bin2hex(random_bytes(32));

        $table->update([
            'qr_token'        => $token,
            'qr_enabled'      => true,
            'qr_generated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'qr_token'        => $token,
                'qr_enabled'      => true,
                'qr_generated_at' => now()->toIso8601String(),
                'qr_url'          => $this->buildMenuUrl($token),
            ],
        ]);
    }

    // ── PATCH /api/v1/tables/{id}/qr/toggle ───────────────────────────────────
    // Enable or disable QR for a table
    public function toggle(Request $request, string $id): JsonResponse
    {
        $table = RestaurantTable::findOrFail($id);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        if ($validated['enabled']) {
            $this->assertFeature(auth()->user()->tenant_id, 'qr_self_order');

            if (empty($table->qr_token)) {
                $table->qr_token        = bin2hex(random_bytes(32));
                $table->qr_generated_at = now();
            }
        }

        $table->qr_enabled = $validated['enabled'];
        $table->save();

        return response()->json([
            'success' => true,
            'data'    => [
                'qr_enabled' => $table->qr_enabled,
                'qr_url'     => $table->qr_token ? $this->buildMenuUrl($table->qr_token) : null,
            ],
        ]);
    }

    // ── GET /api/v1/tables/{id}/qr ────────────────────────────────────────────
    // Return QR code info + menu URL
    public function show(string $id): JsonResponse
    {
        $table = RestaurantTable::findOrFail($id);

        if (empty($table->qr_token)) {
            return response()->json([
                'success' => false,
                'message' => 'QR belum di-generate untuk meja ini.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'qr_token'        => $table->qr_token,
                'qr_enabled'      => $table->qr_enabled,
                'qr_generated_at' => $table->qr_generated_at?->toIso8601String(),
                'qr_url'          => $this->buildMenuUrl($table->qr_token),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function buildMenuUrl(string $token): string
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
        return "{$frontendUrl}/menu/table/{$token}";
    }

    private function assertFeature(string $tenantId, string $featureKey): void
    {
        $subscription = Subscription::with('plan.features')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>=', now())
                  ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('ends_at', '>=', now()));
            })
            ->latest()
            ->first();

        if (!$subscription?->plan?->hasFeature($featureKey)) {
            abort(403, 'Fitur QR Self Order tidak tersedia pada paket Anda. Silakan upgrade.');
        }
    }

    private function getFeatureValue(string $tenantId, string $featureKey): ?string
    {
        $subscription = Subscription::with('plan.features')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>=', now())
                  ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('ends_at', '>=', now()));
            })
            ->latest()
            ->first();

        return $subscription?->plan?->getFeatureValue($featureKey);
    }
}
