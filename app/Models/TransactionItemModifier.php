<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItemModifier extends Model
{
    use HasUuids;

    protected $table = 'transaction_item_modifiers';

    protected $fillable = [
        'transaction_item_id',
        'modifier_id',
        'modifier_name',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class);
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }
}
