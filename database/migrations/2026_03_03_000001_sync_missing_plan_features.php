<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sync missing plan_features rows for all existing plans.
 *
 * This migration ensures that every plan has all known feature keys,
 * using sensible defaults based on the plan's slug.
 * It uses updateOrCreate logic so running it twice is safe.
 */
return new class extends Migration
{
    /**
     * Feature defaults per plan slug.
     * 'true'  = feature is enabled for this plan
     * 'false' = feature is disabled / upgrade required
     */
    private array $planFeatures = [
        'starter' => [
            'pos_basic'         => 'true',
            'inventory_basic'   => 'true',
            'inventory_recipe'  => 'false',
            'modifiers'         => 'false',
            'customers'         => 'true',
            'expenses'          => 'false',
            'kitchen_display'   => 'false',
            'advanced_reports'  => 'false',
            'audit_log'         => 'false',
            'shift_management'  => 'true',
        ],
        'professional' => [
            'pos_basic'         => 'true',
            'inventory_basic'   => 'true',
            'inventory_recipe'  => 'true',
            'modifiers'         => 'true',
            'customers'         => 'true',
            'expenses'          => 'true',
            'kitchen_display'   => 'true',
            'advanced_reports'  => 'true',
            'audit_log'         => 'false',
            'shift_management'  => 'true',
        ],
        'enterprise' => [
            'pos_basic'         => 'true',
            'inventory_basic'   => 'true',
            'inventory_recipe'  => 'true',
            'modifiers'         => 'true',
            'customers'         => 'true',
            'expenses'          => 'true',
            'kitchen_display'   => 'true',
            'advanced_reports'  => 'true',
            'audit_log'         => 'true',
            'shift_management'  => 'true',
            'api_access'        => 'true',
            'white_label'       => 'true',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->planFeatures as $slug => $features) {
            $plan = DB::table('plans')->where('slug', $slug)->first();

            if (!$plan) {
                continue; // plan not seeded yet, skip
            }

            foreach ($features as $key => $value) {
                $exists = DB::table('plan_features')
                    ->where('plan_id', $plan->id)
                    ->where('feature_key', $key)
                    ->exists();

                if (!$exists) {
                    DB::table('plan_features')->insert([
                        'plan_id'       => $plan->id,
                        'feature_key'   => $key,
                        'feature_value' => $value,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // No destructive rollback — we only insert missing rows.
        // To revert, manually delete the rows or re-run PlanSeeder.
    }
};
