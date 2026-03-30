<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('code')->unique();
            $blueprint->string('name');
            $blueprint->text('description')->nullable();
            $blueprint->enum('type', ['percentage', 'fixed'])->default('percentage');
            $blueprint->decimal('value', 15, 2);
            $blueprint->decimal('min_purchase_amount', 15, 2)->default(0);
            $blueprint->integer('max_uses_total')->nullable();
            $blueprint->integer('uses_count')->default(0);
            $blueprint->integer('max_uses_per_user')->default(1);
            $blueprint->json('applicable_plan_ids')->nullable(); // JSON array of plan IDs
            $blueprint->timestamp('valid_from')->nullable();
            $blueprint->timestamp('valid_until')->nullable();
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
