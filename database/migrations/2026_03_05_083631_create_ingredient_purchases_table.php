<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ingredient_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('expense_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('ingredient_id')->constrained()->onDelete('cascade');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('total_price', 15, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_purchases');
    }
};
