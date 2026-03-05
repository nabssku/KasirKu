<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'preparing' and 'ready' to Transaction status enum
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM(
            'pending_payment','pending','in_progress','paid',
            'preparing','ready','completed','cancelled','refunded'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM(
            'pending_payment','pending','in_progress','paid',
            'completed','cancelled','refunded'
        ) NOT NULL DEFAULT 'pending'");
    }
};
