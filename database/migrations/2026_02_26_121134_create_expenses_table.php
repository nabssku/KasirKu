<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index()->constrained()->onDelete('cascade');
            $table->foreignUuid('outlet_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('category_id')->constrained('expense_categories')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignUuid('shift_id')->nullable()->constrained('shifts')->onDelete('set null');
            
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'other'])->default('cash');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->date('date');
            $table->string('attachment')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
