<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $blueprint) {
            $blueprint->foreignId('discount_id')->nullable()->constrained()->nullOnDelete();
            $blueprint->decimal('discount_amount', 15, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $blueprint) {
            $blueprint->dropConstrainedForeignId('discount_id');
            $blueprint->dropColumn('discount_amount');
        });
    }
};
