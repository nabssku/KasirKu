<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Services\InventoryService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected PlanLimitService $planLimit
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Ingredient::query()
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('current_stock', '<=', 'min_stock'))
            ->orderBy('name');

        $ingredients = $query->paginate((int) $request->query('per_page', 25));

        return response()->json(['success' => true, 'data' => $ingredients]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id'     => ['nullable', 'uuid', 'exists:outlets,id'],
            'name'          => ['required', 'string', 'max:255'],
            'unit'          => ['required', 'string', 'max:50'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->planLimit->enforce(auth()->user()->tenant_id, 'ingredients');

        $ingredient = Ingredient::create($validated);

        return response()->json(['success' => true, 'data' => $ingredient], 201);
    }

    public function show(string $id): JsonResponse
    {
        $ingredient = Ingredient::with(['stockMovements' => fn ($q) => $q->latest()->take(20)])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $ingredient]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $ingredient = Ingredient::findOrFail($id);

        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'unit'          => ['sometimes', 'string', 'max:50'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'min_stock'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $ingredient->update($validated);

        return response()->json(['success' => true, 'data' => $ingredient]);
    }

    public function destroy(string $id): JsonResponse
    {
        Ingredient::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Ingredient deleted.']);
    }

    /**
     * POST /ingredients/{id}/adjust
     * Adjust stock (in, out, adjustment, waste)
     */
    public function adjustStock(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'type'     => ['required', 'string', 'in:in,out,adjustment,waste'],
            'notes'    => ['nullable', 'string'],
        ]);

        $movement = $this->inventoryService->adjustStock(
            $id,
            $validated['quantity'],
            $validated['type'],
            $validated['notes'] ?? ''
        );

        return response()->json(['success' => true, 'data' => $movement], 201);
    }

    /**
     * GET /ingredients/low-stock
     */
    public function lowStock(): JsonResponse
    {
        $ingredients = $this->inventoryService->getLowStockIngredients(
            auth()->user()->tenant_id,
            auth()->user()->outlet_id
        );

        return response()->json(['success' => true, 'data' => $ingredients]);
    }
}
