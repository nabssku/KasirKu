<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(protected ShiftService $shiftService) {}

    public function current(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id', auth()->user()->outlet_id);

        if (!$outletId) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $shift = $this->shiftService->getCurrentShift($outletId);

        return response()->json(['success' => true, 'data' => $shift]);
    }

    public function index(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id', auth()->user()->outlet_id);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $cashierId = $request->query('cashier_id');

        $query = Shift::with(['openedBy', 'closedBy', 'outlet'])
            ->orderByDesc('opened_at');

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        if ($startDate) {
            $query->whereDate('opened_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('opened_at', '<=', $endDate);
        }

        if ($cashierId) {
            $query->where('opened_by', $cashierId);
        }

        $shifts = $query->paginate((int) $request->query('per_page', 15));

        return response()->json(['success' => true, 'data' => $shifts]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id'    => ['required', 'uuid', 'exists:outlets,id'],
            'opening_cash' => ['nullable', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string'],
        ]);

        $shift = $this->shiftService->openShift($validated);

        return response()->json(['success' => true, 'data' => $shift], 201);
    }

    public function close(Request $request, string $id): JsonResponse
    {
        $shift = Shift::findOrFail($id);

        $validated = $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string'],
        ]);

        $shift = $this->shiftService->closeShift($shift, $validated);

        return response()->json(['success' => true, 'data' => $shift]);
    }

    public function show(string $id): JsonResponse
    {
        $shift = Shift::with(['openedBy', 'closedBy', 'cashDrawerLogs.user', 'transactions'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $shift]);
    }

    public function addCashLog(Request $request, string $id): JsonResponse
    {
        $shift = Shift::findOrFail($id);

        $validated = $request->validate([
            'type'   => ['required', 'string', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string'],
        ]);

        $log = $this->shiftService->addCashDrawerLog($shift, $validated);

        return response()->json(['success' => true, 'data' => $log], 201);
    }
}
