<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For MySQL, we need to modify the ENUM to add new values
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('dine_in','takeaway','delivery','walk_in','online') DEFAULT 'dine_in'");
    }

    public function down(): void
    {
        // Revert: remove walk_in and online (existing data with these values will be lost)
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('dine_in','takeaway','delivery') DEFAULT 'dine_in'");
    }
};
