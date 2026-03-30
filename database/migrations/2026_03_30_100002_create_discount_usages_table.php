<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_usages', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignUuid('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_usages');
    }
};
