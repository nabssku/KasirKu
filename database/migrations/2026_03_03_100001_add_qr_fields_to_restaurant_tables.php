<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->string('qr_token', 64)->unique()->nullable()->after('sort_order');
            $table->boolean('qr_enabled')->default(false)->after('qr_token');
            $table->timestamp('qr_generated_at')->nullable()->after('qr_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropColumn(['qr_token', 'qr_enabled', 'qr_generated_at']);
        });
    }
};
