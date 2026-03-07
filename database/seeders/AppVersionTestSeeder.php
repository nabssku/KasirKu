<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AppVersionTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\AppVersion::updateOrCreate(
            ['version_code' => 2],
            [
                'version_name' => '1.0.1',
                'file_path' => 'updates/jagokasir-v1.0.1.apk',
                'release_notes' => "• Perbaikan sistem kasir\n• Optimasi performa stok\n• Fitur update otomatis",
                'is_critical' => false,
            ]
        );
    }
}
