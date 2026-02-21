<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index()->constrained()->onDelete('cascade');
            $table->foreignUuid('outlet_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('transaction_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['queued', 'cooking', 'ready', 'served', 'cancelled'])->default('queued');
            $table->string('order_code')->nullable(); // short display code e.g. "#A12"
            $table->string('table_name')->nullable(); // snapshot
            $table->string('type')->default('dine_in'); // snapshot
            $table->text('notes')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kitchen_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kitchen_order_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('transaction_item_id')->nullable()->constrained()->onDelete('set null');
            $table->string('product_name');
            $table->integer('quantity');
            $table->text('modifier_notes')->nullable(); // e.g. "Large, Extra Shot, No Sugar"
            $table->enum('status', ['queued', 'cooking', 'ready'])->default('queued');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_order_items');
        Schema::dropIfExists('kitchen_orders');
    }
};
