<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('outlet_id')->nullable()->after('tenant_id')->constrained()->onDelete('set null');
            $table->boolean('has_recipe')->default(false)->after('is_active');
            $table->integer('prep_time')->default(0)->after('has_recipe'); // minutes
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropColumn(['outlet_id', 'has_recipe', 'prep_time']);
        });
    }
};
