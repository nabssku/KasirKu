<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename legacy keys
        $renames = [
            'audit_logs'           => 'audit_log',
            'inventory_management' => 'inventory_basic',
            'customer_management'  => 'customers',
            'modifier_groups'      => 'modifiers',
            'pos_terminal'         => 'pos_basic',
        ];

        foreach ($renames as $old => $new) {
            // Find all records with the old key
            $records = DB::table('plan_features')->where('feature_key', $old)->get();

            foreach ($records as $record) {
                // Check if the new key already exists for this plan
                $exists = DB::table('plan_features')
                    ->where('plan_id', $record->plan_id)
                    ->where('feature_key', $new)
                    ->exists();

                if (!$exists) {
                    // Rename if new doesn't exist
                    DB::table('plan_features')
                        ->where('id', $record->id)
                        ->update(['feature_key' => $new]);
                } else {
                    // Delete if new already exists (keep the value of the new one or the old one? 
                    // Usually 'true' is better than 'false', but here we just want to avoid duplicates)
                    DB::table('plan_features')->where('id', $record->id)->delete();
                }
            }
        }

        // 2. Remove unknown/unsupported keys
        $unsupported = [
            'multi_outlet',
            'barcode_scanner',
            'receipt_customization',
        ];

        DB::table('plan_features')->whereIn('feature_key', $unsupported)->delete();

        // 3. Ensure canonical features exist for existing plans
        $plans = DB::table('plans')->get();
        $canonicalFeatures = [
            'pos_basic'          => 'true',
            'inventory_basic'    => 'true',
            'inventory_recipe'   => 'false',
            'modifiers'          => 'false',
            'customers'          => 'true',
            'expenses'           => 'false',
            'kitchen_display'    => 'false',
            'advanced_reports'   => 'false',
            'audit_log'          => 'false',
            'shift_management'   => 'true',
            'qr_self_order'      => 'false',
            'max_qr_tables'      => '0',
            'api_access'         => 'false',
            'white_label'        => 'false',
        ];

        foreach ($plans as $plan) {
            foreach ($canonicalFeatures as $key => $defaultValue) {
                $exists = DB::table('plan_features')
                    ->where('plan_id', $plan->id)
                    ->where('feature_key', $key)
                    ->exists();

                if (!$exists) {
                    // For Professional/Enterprise, some defaults should be different
                    $val = $defaultValue;
                    
                    if ($plan->slug === 'professional' || $plan->slug === 'enterprise') {
                        if (in_array($key, ['inventory_recipe', 'modifiers', 'expenses', 'kitchen_display', 'advanced_reports', 'qr_self_order'])) {
                            $val = 'true';
                        }
                        if ($key === 'max_qr_tables') {
                            $val = ($plan->slug === 'enterprise') ? '50' : '10';
                        }
                    }

                    if ($plan->slug === 'enterprise') {
                        if (in_array($key, ['audit_log', 'api_access', 'white_label'])) {
                            $val = 'true';
                        }
                    }

                    DB::table('plan_features')->insert([
                        'plan_id'       => $plan->id,
                        'feature_key'   => $key,
                        'feature_value' => $val,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        }

        // 4. Handle any remaining duplicates (same plan_id + feature_key)
        $duplicates = DB::table('plan_features')
            ->select('plan_id', 'feature_key', DB::raw('COUNT(*) as count'))
            ->groupBy('plan_id', 'feature_key')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $ids = DB::table('plan_features')
                ->where('plan_id', $dup->plan_id)
                ->where('feature_key', $dup->feature_key)
                ->orderBy('id', 'desc')
                ->pluck('id')
                ->toArray();
            
            // Keep the first one (desc order means we keep newest/highest ID), delete others
            array_shift($ids);
            DB::table('plan_features')->whereIn('id', $ids)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to undo this cleanup reliably
    }
};
