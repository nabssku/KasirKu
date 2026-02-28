<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bluetooth_printers', function (Blueprint $table) {
            $table->enum('type', ['cashier', 'kitchen', 'both'])->default('both')->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('bluetooth_printers', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
