<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug'         => 'starter',
                'name'         => 'Starter',
                'price'        => 99000,
                'billing_cycle'=> 'monthly',
                'max_outlets'  => 1,
                'max_users'    => 5,
                'max_products' => 100,
                'description'  => 'Perfect for a single-outlet business getting started.',
                'features' => [
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
                ],
            ],
            [
                'slug'         => 'professional',
                'name'         => 'Professional',
                'price'        => 249000,
                'billing_cycle'=> 'monthly',
                'max_outlets'  => 3,
                'max_users'    => 20,
                'max_products' => 500,
                'description'  => 'Ideal for growing restaurants with multiple outlets.',
                'features' => [
                    'pos_basic'          => 'true',
                    'inventory_basic'    => 'true',
                    'inventory_recipe'   => 'true',
                    'modifiers'          => 'true',
                    'customers'          => 'true',
                    'expenses'           => 'true',
                    'kitchen_display'    => 'true',
                    'advanced_reports'   => 'true',
                    'audit_log'          => 'false',
                    'shift_management'   => 'true',
                    'qr_self_order'      => 'true',
                    'max_qr_tables'      => '10',
                    'api_access'         => 'false',
                    'white_label'        => 'false',
                ],
            ],
            [
                'slug'         => 'enterprise',
                'name'         => 'Enterprise',
                'price'        => 499000,
                'billing_cycle'=> 'monthly',
                'max_outlets'  => 999,
                'max_users'    => 999,
                'max_products' => 9999,
                'description'  => 'Unlimited outlets, users, and products for chains.',
                'features' => [
                    'pos_basic'          => 'true',
                    'inventory_basic'    => 'true',
                    'inventory_recipe'   => 'true',
                    'modifiers'          => 'true',
                    'customers'          => 'true',
                    'expenses'           => 'true',
                    'kitchen_display'    => 'true',
                    'advanced_reports'   => 'true',
                    'audit_log'          => 'true',
                    'shift_management'   => 'true',
                    'api_access'         => 'true',
                    'white_label'        => 'true',
                    'qr_self_order'      => 'true',
                    'max_qr_tables'      => '50',
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $features = $planData['features'];
            unset($planData['features']);

            $plan = Plan::updateOrCreate(['slug' => $planData['slug']], $planData);

            foreach ($features as $key => $value) {
                PlanFeature::updateOrCreate(
                    ['plan_id' => $plan->id, 'feature_key' => $key],
                    ['feature_value' => $value]
                );
            }
        }
    }
}
