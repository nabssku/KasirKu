<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_order_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('outlet_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('table_id')->constrained('restaurant_tables')->onDelete('cascade');
            $table->string('session_token', 64)->unique();
            $table->json('cart_data')->nullable();
            $table->enum('status', ['active', 'submitted', 'expired'])->default('active');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'status']);
            $table->index(['table_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_order_sessions');
    }
};
