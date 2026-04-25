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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique(); // e.g., 'cash', 'qris', 'bank_transfer'
            $table->string('category')->default('other'); // 'cash', 'e-wallet', 'bank', etc.
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('outlet_payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('outlet_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('payment_method_id')->constrained('payment_methods')->onDelete('cascade');
            $table->boolean('is_enabled')->default(true);
            $table->json('config')->nullable(); // For storing bank account numbers, merchant IDs, etc.
            $table->timestamps();

            $table->unique(['outlet_id', 'payment_method_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlet_payment_methods');
        Schema::dropIfExists('payment_methods');
    }
};
