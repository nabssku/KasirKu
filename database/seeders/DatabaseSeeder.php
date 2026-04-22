<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles, Permissions, Plans
        $this->call([
            RolePermissionSeeder::class,
            PlanSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // 2. Create a Demo Tenant
        $tenant = Tenant::create([
            'name'   => 'Warung Kopi Nusantara',
            'email'  => 'demo@jagokasir.store',
            'status' => 'active',
            'trial_ends_at'        => now()->addDays(14),
            'subscription_ends_at' => now()->addYear(),
        ]);

        // Bind tenant context for BelongsToTenant trait
        app()->instance('current_tenant_id', $tenant->id);

        // 3. Create demo Outlet
        $outlet = Outlet::create([
            'tenant_id'      => $tenant->id,
            'name'           => 'Outlet Pusat',
            'address'        => 'Jl. Sudirman No. 1, Jakarta',
            'phone'          => '021-5551234',
            'tax_rate'       => 11.00,
            'service_charge' => 5.00,
            'is_active'      => true,
        ]);

        // 4. Create Users with roles
        $roles = [
            ['name' => 'Budi Owner',   'email' => 'owner@demo.com',   'role' => 'owner'],
            ['name' => 'Citra Admin',  'email' => 'admin@demo.com',   'role' => 'admin'],
            ['name' => 'Deni Kasir',   'email' => 'cashier@demo.com', 'role' => 'cashier'],
            ['name' => 'Eka Kitchen',  'email' => 'kitchen@demo.com', 'role' => 'kitchen'],
        ];

        foreach ($roles as $userData) {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'outlet_id' => $outlet->id,
                'name'      => $userData['name'],
                'email'     => $userData['email'],
                'password'  => Hash::make('password'),
                'is_active' => true,
            ]);

            $role = Role::where('slug', $userData['role'])->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }
        }

        // 5. Create trial subscription
        Subscription::create([
            'tenant_id'     => $tenant->id,
            'plan_id'       => \App\Models\Plan::where('slug', 'professional')->first()?->id,
            'status'        => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'starts_at'     => now(),
            'ends_at'       => now()->addDays(14),
        ]);

        // 6. Seed categories and products
        $category = \App\Models\Category::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Minuman',
            'slug'      => 'minuman',
        ]);

        $categoryMakanan = \App\Models\Category::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Makanan',
            'slug'      => 'makanan',
        ]);

        \App\Models\Product::create([
            'tenant_id'   => $tenant->id,
            'outlet_id'   => $outlet->id,
            'category_id' => $category->id,
            'name'        => 'Kopi Hitam',
            'price'       => 12000,
            'cost_price'  => 4000,
            'stock'       => 99,
            'min_stock'   => 10,
            'is_active'   => true,
        ]);

        \App\Models\Product::create([
            'tenant_id'   => $tenant->id,
            'outlet_id'   => $outlet->id,
            'category_id' => $category->id,
            'name'        => 'Es Teh Manis',
            'price'       => 8000,
            'cost_price'  => 2000,
            'stock'       => 99,
            'min_stock'   => 10,
            'is_active'   => true,
        ]);

        \App\Models\Product::create([
            'tenant_id'   => $tenant->id,
            'outlet_id'   => $outlet->id,
            'category_id' => $categoryMakanan->id,
            'name'        => 'Nasi Goreng Spesial',
            'price'       => 28000,
            'cost_price'  => 12000,
            'stock'       => 50,
            'min_stock'   => 5,
            'is_active'   => true,
        ]);

        // 7. Create demo tables
        foreach (range(1, 6) as $i) {
            \App\Models\RestaurantTable::create([
                'tenant_id'  => $tenant->id,
                'outlet_id'  => $outlet->id,
                'name'       => 'Table ' . $i,
                'capacity'   => ($i <= 4) ? 4 : 6,
                'status'     => 'available',
                'floor'      => 'Ground Floor',
                'sort_order' => $i,
            ]);
        }

        echo "\n✅ Demo seeded — Login with:\n";
        echo "   owner@demo.com / password\n";
        echo "   cashier@demo.com / password\n";
        echo "   kitchen@demo.com / password\n";
    }
}
