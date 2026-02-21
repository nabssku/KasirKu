<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function __construct(protected PlanLimitService $planLimit) {}

    public function index(Request $request): JsonResponse
    {
        $tables = RestaurantTable::query()
            ->when($request->query('outlet_id'), fn ($q, $v) => $q->where('outlet_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $tables]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id'  => ['nullable', 'uuid', 'exists:outlets,id'],
            'name'       => ['required', 'string', 'max:100'],
            'capacity'   => ['nullable', 'integer', 'min:1'],
            'floor'      => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        // Auto-fill outlet_id from authenticated user if not provided
        if (empty($validated['outlet_id'])) {
            $validated['outlet_id'] = auth()->user()->outlet_id;
        }

        $this->planLimit->enforce(auth()->user()->tenant_id, 'tables');

        $table = RestaurantTable::create($validated);

        return response()->json(['success' => true, 'data' => $table], 201);
    }

    public function show(string $id): JsonResponse
    {
        $table = RestaurantTable::with('activeTransaction')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $table]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $table = RestaurantTable::findOrFail($id);

        $validated = $request->validate([
            'name'       => ['sometimes', 'string', 'max:100'],
            'capacity'   => ['nullable', 'integer', 'min:1'],
            'floor'      => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $table->update($validated);

        return response()->json(['success' => true, 'data' => $table]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $table = RestaurantTable::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:available,occupied,reserved,dirty'],
        ]);

        $table->update($validated);

        return response()->json(['success' => true, 'data' => $table]);
    }

    public function destroy(string $id): JsonResponse
    {
        RestaurantTable::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Table deleted.']);
    }
}
