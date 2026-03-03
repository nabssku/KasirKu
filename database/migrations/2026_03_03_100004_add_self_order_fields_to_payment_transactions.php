<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignUuid('transaction_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('transactions')
                ->onDelete('set null');

            $table->foreignUuid('outlet_id')
                ->nullable()
                ->after('transaction_id')
                ->constrained('outlets')
                ->onDelete('set null');

            $table->timestamp('expires_at')->nullable()->after('paid_at');
        });

        // Extend type enum to include self_order_payment
        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN type ENUM(
            'subscription','topup','self_order_payment'
        ) NOT NULL DEFAULT 'subscription'");
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropForeign(['outlet_id']);
            $table->dropColumn(['transaction_id', 'outlet_id', 'expires_at']);
        });

        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN type ENUM(
            'subscription','topup'
        ) NOT NULL DEFAULT 'subscription'");
    }
};
