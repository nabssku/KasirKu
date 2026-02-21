<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index()->constrained()->onDelete('cascade');
            $table->enum('type', ['subscription', 'topup'])->default('subscription');
            $table->decimal('amount', 15, 2);
            $table->string('gateway')->default('midtrans'); // midtrans, manual
            $table->string('gateway_order_id')->nullable(); // Midtrans order_id
            $table->string('gateway_transaction_id')->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded'])->default('pending');
            $table->json('gateway_payload')->nullable(); // full Midtrans response
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // Update subscriptions table to link to plans
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add plan_id FK and trial_ends_at — status/starts_at/ends_at already exist
            $table->foreignId('plan_id')->nullable()->after('tenant_id')->constrained()->onDelete('set null');
            $table->timestamp('trial_ends_at')->nullable()->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'trial_ends_at']);
        });
        Schema::dropIfExists('payment_transactions');
    }
};
