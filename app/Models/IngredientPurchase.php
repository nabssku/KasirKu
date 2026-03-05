<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientPurchase extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'expense_id',
        'ingredient_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'quantity'    => 'double',
        'unit_price'  => 'double',
        'total_price' => 'double',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
