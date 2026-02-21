<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles & permissions exist first
        $this->call(RolePermissionSeeder::class);

        $superAdminRole = Role::where('slug', 'super_admin')->first();

        if (!$superAdminRole) {
            $this->command->error('super_admin role not found. Run RolePermissionSeeder first.');
            return;
        }

        // Create super admin user (no tenant, no outlet)
        $user = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'superadmin@kasirku.com'],
            [
                'name'      => 'Super Admin',
                'password'  => Hash::make('superadmin123'),
                'tenant_id' => null,
                'outlet_id' => null,
                'is_active' => true,
            ]
        );

        // Attach super_admin role if not already attached
        if (!$user->roles()->where('slug', 'super_admin')->exists()) {
            $user->roles()->attach($superAdminRole->id);
        }

        echo "\n✅ Super Admin seeded — Login with:\n";
        echo "   superadmin@kasirku.com / superadmin123\n";
    }
}
