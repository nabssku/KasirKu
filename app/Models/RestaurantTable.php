<?php

namespace App\Models;

use App\Core\Traits\Auditable;

use App\Core\Traits\BelongsToOutlet;
use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestaurantTable extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, BelongsToOutlet, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'name',
        'capacity',
        'status',
        'floor',
        'sort_order',
    ];

    protected $casts = [
        'capacity'   => 'integer',
        'sort_order' => 'integer',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function activeTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'table_id')->whereIn('status', ['pending', 'in_progress']);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
