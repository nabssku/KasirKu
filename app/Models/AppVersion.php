<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = [
        'version_name',
        'version_code',
        'file_path',
        'release_notes',
        'is_critical',
    ];

    public function getDownloadUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }
}
