<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('invoice_id')->nullable()->after('gateway_order_id');
            $table->string('payment_url')->nullable()->after('invoice_id');
            $table->unsignedBigInteger('final_amount')->nullable()->after('payment_url');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['invoice_id', 'payment_url', 'final_amount']);
        });
    }
};
