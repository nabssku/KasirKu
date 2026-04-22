<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'category_type',
        'image',
        'description',
        'is_active',
        'data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'data' => 'json',
    ];
}
