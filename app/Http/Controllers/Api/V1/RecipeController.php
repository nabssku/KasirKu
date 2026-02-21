<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function show(string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $recipe  = $product->recipe()->with('items.ingredient')->first();

        return response()->json(['success' => true, 'data' => $recipe]);
    }

    public function upsert(Request $request, string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'yield'             => ['nullable', 'integer', 'min:1'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            'items.*.quantity'  => ['required', 'numeric', 'min:0.0001'],
        ]);

        return DB::transaction(function () use ($product, $validated) {
            $recipe = Recipe::updateOrCreate(
                ['product_id' => $product->id],
                ['yield' => $validated['yield'] ?? 1]
            );

            // Replace all recipe items
            $recipe->items()->delete();
            foreach ($validated['items'] as $item) {
                $recipe->items()->create([
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity'      => $item['quantity'],
                ]);
            }

            // Mark product as having a recipe
            $product->update(['has_recipe' => true]);

            return response()->json([
                'success' => true,
                'data'    => $recipe->load('items.ingredient'),
            ], 201);
        });
    }

    public function destroy(string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $product->recipe()?->delete();
        $product->update(['has_recipe' => false]);

        return response()->json(['success' => true, 'message' => 'Recipe deleted.']);
    }
}
