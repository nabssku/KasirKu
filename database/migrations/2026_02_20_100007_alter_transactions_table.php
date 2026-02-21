<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // outlet_id FK – outlets table IS already created (migration 100001)
            $table->foreignUuid('outlet_id')->nullable()->after('tenant_id')->constrained()->onDelete('set null');

            // table_id and shift_id stored WITHOUT FK for now (added later after their tables are created)
            $table->uuid('table_id')->nullable()->after('customer_id');
            $table->uuid('shift_id')->nullable()->after('table_id');

            $table->enum('type', ['dine_in', 'takeaway', 'delivery'])->default('dine_in')->after('shift_id');
            $table->decimal('tax_rate', 5, 2)->default(10)->after('discount');
            $table->decimal('service_charge', 15, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropColumn(['outlet_id', 'table_id', 'shift_id', 'type', 'tax_rate', 'service_charge']);
        });
    }
};
