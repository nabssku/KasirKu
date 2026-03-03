<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Services\SelfOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public API controller — no authentication required.
 * All endpoints are rate-limited (see routes/api.php).
 */
class SelfOrderController extends Controller
{
    public function __construct(protected SelfOrderService $service) {}

    // ── GET /api/v1/public/table/{qr_token} ───────────────────────────────────
    // Validate QR, return outlet and table info for the Web Menu
    public function resolveTable(string $qrToken): JsonResponse
    {
        $result = $this->service->resolveQrToken($qrToken);

        return response()->json([
            'success' => true,
            'data'    => [
                'table'  => [
                    'id'   => $result['table']->id,
                    'name' => $result['table']->name,
                ],
                'outlet' => [
                    'id'             => $result['outlet']->id,
                    'name'           => $result['outlet']->name,
                    'tax_rate'       => $result['outlet']->tax_rate,
                    'service_charge' => $result['outlet']->service_charge,
                ],
            ],
        ]);
    }

    // ── GET /api/v1/public/menu/{outlet_id} ───────────────────────────────────
    // Fetch categorised menu for a given outlet
    public function menu(string $outletId): JsonResponse
    {
        $menu = $this->service->getPublicMenu($outletId);

        return response()->json(['success' => true, 'data' => $menu]);
    }

    // ── POST /api/v1/public/self-order/session ────────────────────────────────
    // Create (or renew) a cart session for a table
    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_token' => ['required', 'string'],
        ]);

        $result  = $this->service->resolveQrToken($validated['qr_token']);
        $session = $this->service->createSession($result['table'], $request);

        return response()->json([
            'success' => true,
            'data'    => [
                'session_token' => $session->session_token,
                'expires_at'    => $session->expires_at->toIso8601String(),
                'table'         => [
                    'id'   => $result['table']->id,
                    'name' => $result['table']->name,
                ],
                'outlet' => [
                    'id'             => $result['outlet']->id,
                    'name'           => $result['outlet']->name,
                    'tax_rate'       => $result['outlet']->tax_rate,
                    'service_charge' => $result['outlet']->service_charge,
                ],
            ],
        ], 201);
    }

    // ── POST /api/v1/public/self-order ────────────────────────────────────────
    // Submit order and receive QRIS payment URL
    public function submitOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token'     => ['required', 'string'],
            'customer_name'     => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:500'],
            'redirect_url'      => ['nullable', 'url'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.product_id'=> ['required', 'uuid'],
            'items.*.quantity'  => ['required', 'integer', 'min:1'],
            'items.*.modifiers' => ['nullable', 'array'],
        ]);

        $result = $this->service->submitOrder(
            $validated['session_token'],
            $validated,
            $request
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'invoice_number' => $result['transaction']->invoice_number,
                'grand_total'    => $result['transaction']->grand_total,
                'payment_url'    => $result['payment_url'],
                'invoice_id'     => $result['invoice_id'],
                'expires_at'     => $result['expires_at'],
                'session_token'  => $result['session_token'],
            ],
        ], 201);
    }

    // ── GET /api/v1/public/self-order/{sessionToken}/status ───────────────────
    // Poll payment & kitchen status
    public function orderStatus(string $sessionToken): JsonResponse
    {
        $status = $this->service->getOrderStatus($sessionToken);

        return response()->json(['success' => true, 'data' => $status]);
    }
}
