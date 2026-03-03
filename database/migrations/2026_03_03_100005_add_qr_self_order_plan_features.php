<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add qr_self_order feature to all existing plans
        // By default: free plan gets false, pro/business plans get true
        $plans = DB::table('plans')->get();

        foreach ($plans as $plan) {
            $isPro = in_array(strtolower($plan->slug ?? $plan->name), [
                'pro', 'business', 'enterprise', 'premium',
            ]);

            // qr_self_order feature
            DB::table('plan_features')->insertOrIgnore([
                'plan_id'       => $plan->id,
                'feature_key'   => 'qr_self_order',
                'feature_value' => $isPro ? 'true' : 'false',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // max_qr_tables feature
            DB::table('plan_features')->insertOrIgnore([
                'plan_id'       => $plan->id,
                'feature_key'   => 'max_qr_tables',
                'feature_value' => $isPro ? '50' : '0',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('plan_features')
            ->whereIn('feature_key', ['qr_self_order', 'max_qr_tables'])
            ->delete();
    }
};
