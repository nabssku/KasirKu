<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('max_categories')->default(10)->after('max_products');
            $table->integer('max_ingredients')->default(25)->after('max_categories');
            $table->integer('max_modifiers')->default(10)->after('max_ingredients');
            $table->integer('trial_days')->default(14)->after('max_modifiers');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('snap_token')->nullable()->after('gateway_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_categories', 'max_ingredients', 'max_modifiers', 'trial_days']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('snap_token');
        });
    }
};
