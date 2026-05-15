<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('source')->default('cashier')->after('type');
            $table->string('customer_name')->nullable()->after('source'); // for self-order guest name
            $table->timestamp('payment_expires_at')->nullable()->after('customer_name');
        });

        // Extend status enum to include pending_payment
        // SQLite doesn't support ALTER COLUMN for enums, so we handle this via the model
        // For MySQL, run this raw statement:
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('status')->default('pending_payment')->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['source', 'customer_name', 'payment_expires_at']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }
};
