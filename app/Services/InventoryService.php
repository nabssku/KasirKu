<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Automatically deduct ingredient stock for all items in a transaction
     * based on each product's recipe.
     */
    public function deductByTransaction(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            foreach ($transaction->items as $item) {
                $product = $item->product ?? Product::find($item->product_id);

                if (!$product || !$product->has_recipe) {
                    continue;
                }

                $recipe = $product->recipe()->with('items.ingredient')->first();
                if (!$recipe) {
                    continue;
                }

                $portionsNeeded = $item->quantity; // each sold unit = 1 recipe yield

                foreach ($recipe->items as $recipeItem) {
                    $ingredient = $recipeItem->ingredient;
                    if (!$ingredient) continue;

                    $qtyToDeduct  = $recipeItem->quantity * $portionsNeeded;
                    $qtyBefore    = $ingredient->current_stock;
                    $qtyAfter     = max(0, $qtyBefore - $qtyToDeduct);

                    // Update ingredient stock
                    $ingredient->update(['current_stock' => $qtyAfter]);

                    // Record movement
                    StockMovement::create([
                        'tenant_id'      => $transaction->tenant_id,
                        'outlet_id'      => $transaction->outlet_id,
                        'ingredient_id'  => $ingredient->id,
                        'type'           => 'out',
                        'quantity'       => $qtyToDeduct,
                        'quantity_before' => $qtyBefore,
                        'quantity_after'  => $qtyAfter,
                        'reference_type' => Transaction::class,
                        'reference_id'   => $transaction->id,
                        'notes'          => "Auto-deducted from transaction #{$transaction->invoice_number}",
                        'created_by'     => auth()->id(),
                    ]);
                }
            }
        });
    }

    /**
     * Manual stock adjustment (admin action).
     */
    public function adjustStock(
        string $ingredientId,
        float  $quantity,
        string $type,   // 'in', 'out', 'adjustment', 'waste'
        string $notes = ''
    ): StockMovement {
        $ingredient  = Ingredient::findOrFail($ingredientId);
        $qtyBefore   = (float) $ingredient->current_stock;

        $qtyAfter = match($type) {
            'in'         => $qtyBefore + $quantity,
            'out', 'waste' => max(0, $qtyBefore - $quantity),
            'adjustment' => $quantity, // absolute value
            default      => $qtyBefore,
        };

        $ingredient->update(['current_stock' => $qtyAfter]);

        return StockMovement::create([
            'tenant_id'       => auth()->user()->tenant_id,
            'outlet_id'       => auth()->user()->outlet_id,
            'ingredient_id'   => $ingredient->id,
            'type'            => $type,
            'quantity'        => abs($qtyAfter - $qtyBefore),
            'quantity_before' => $qtyBefore,
            'quantity_after'  => $qtyAfter,
            'notes'           => $notes,
            'created_by'      => auth()->id(),
        ]);
    }

    public function getLowStockIngredients(string $tenantId, ?string $outletId = null)
    {
        return Ingredient::where('tenant_id', $tenantId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->get();
    }
}
