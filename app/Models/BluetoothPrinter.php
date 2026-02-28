<?php

namespace App\Models;

use App\Core\Traits\BelongsToOutlet;
use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BluetoothPrinter extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, BelongsToOutlet, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'name',
        'mac_address',
        'is_default',
        'type',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
