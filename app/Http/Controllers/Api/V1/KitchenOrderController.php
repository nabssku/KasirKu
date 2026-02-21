<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KitchenOrder;
use App\Services\KitchenOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenOrderController extends Controller
{
    public function __construct(protected KitchenOrderService $kitchenOrderService) {}

    public function index(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id', auth()->user()->outlet_id);
        $statuses = $request->query('statuses', 'queued,cooking,ready');
        $statusArr = array_filter(explode(',', $statuses));

        $orders = $this->kitchenOrderService->getQueueByOutlet($outletId, $statusArr);

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function show(string $id): JsonResponse
    {
        $order = KitchenOrder::with('items')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $order]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $order = KitchenOrder::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:cooking,ready,served,cancelled'],
        ]);

        $order = $this->kitchenOrderService->updateStatus($order, $validated['status']);

        return response()->json(['success' => true, 'data' => $order]);
    }
}
