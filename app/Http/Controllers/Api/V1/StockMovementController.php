<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Ingredient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $movements = StockMovement::with(['ingredient', 'createdBy'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->latest()
            ->paginate((int) $request->query('per_page', 50));

        return response()->json(['success' => true, 'data' => $movements->items(), 'meta' => $movements]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            'type'          => ['required', 'string', 'in:in,out,adjustment,waste'],
            'quantity'      => ['required', 'numeric', 'min:0.0001'],
            'notes'         => ['nullable', 'string'],
        ]);

        $ingredient = Ingredient::findOrFail($validated['ingredient_id']);

        $before = (float) $ingredient->current_stock;
        $qty = (float) $validated['quantity'];
        $type = $validated['type'];

        if ($type === 'in') {
            $after = $before + $qty;
        } elseif ($type === 'out' || $type === 'waste') {
            $after = max(0, $before - $qty);
        } else { // adjustment
            $after = max(0, $qty);
        }

        $ingredient->update(['current_stock' => $after]);

        $movement = StockMovement::create([
            'ingredient_id'   => $ingredient->id,
            'tenant_id'       => auth()->user()->tenant_id,
            'outlet_id'       => auth()->user()->outlet_id,
            'type'            => $type,
            'quantity'        => $qty,
            'quantity_before' => $before,
            'quantity_after'  => $after,
            'notes'           => $validated['notes'] ?? null,
            'created_by'      => auth()->id(),
        ]);

        return response()->json(['success' => true, 'data' => $movement->load('ingredient')], 201);
    }
}
