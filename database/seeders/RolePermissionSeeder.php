<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Define all permissions ─────────────────────────────────────────────
        $permissions = [
            // Outlets
            ['slug' => 'outlets:read',   'name' => 'View Outlets',    'group' => 'outlets'],
            ['slug' => 'outlets:create', 'name' => 'Create Outlet',   'group' => 'outlets'],
            ['slug' => 'outlets:update', 'name' => 'Update Outlet',   'group' => 'outlets'],
            ['slug' => 'outlets:delete', 'name' => 'Delete Outlet',   'group' => 'outlets'],
            // Users
            ['slug' => 'users:read',     'name' => 'View Users',      'group' => 'users'],
            ['slug' => 'users:create',   'name' => 'Create User',     'group' => 'users'],
            ['slug' => 'users:update',   'name' => 'Update User',     'group' => 'users'],
            ['slug' => 'users:delete',   'name' => 'Delete User',     'group' => 'users'],
            // Products
            ['slug' => 'products:read',  'name' => 'View Products',   'group' => 'products'],
            ['slug' => 'products:create','name' => 'Create Product',  'group' => 'products'],
            ['slug' => 'products:update','name' => 'Update Product',  'group' => 'products'],
            ['slug' => 'products:delete','name' => 'Delete Product',  'group' => 'products'],
            // Categories
            ['slug' => 'categories:read',  'name' => 'View Categories',  'group' => 'categories'],
            ['slug' => 'categories:create','name' => 'Create Category',  'group' => 'categories'],
            ['slug' => 'categories:update','name' => 'Update Category',  'group' => 'categories'],
            ['slug' => 'categories:delete','name' => 'Delete Category',  'group' => 'categories'],
            // Modifiers
            ['slug' => 'modifiers:read',  'name' => 'View Modifiers',   'group' => 'modifiers'],
            ['slug' => 'modifiers:create','name' => 'Create Modifier',  'group' => 'modifiers'],
            ['slug' => 'modifiers:update','name' => 'Update Modifier',  'group' => 'modifiers'],
            ['slug' => 'modifiers:delete','name' => 'Delete Modifier',  'group' => 'modifiers'],
            // Inventory
            ['slug' => 'ingredients:read',   'name' => 'View Ingredients',  'group' => 'inventory'],
            ['slug' => 'ingredients:create', 'name' => 'Create Ingredient', 'group' => 'inventory'],
            ['slug' => 'ingredients:update', 'name' => 'Update Ingredient', 'group' => 'inventory'],
            ['slug' => 'ingredients:delete', 'name' => 'Delete Ingredient', 'group' => 'inventory'],
            ['slug' => 'ingredients:adjust', 'name' => 'Adjust Stock',      'group' => 'inventory'],
            ['slug' => 'recipes:manage',     'name' => 'Manage Recipes',    'group' => 'inventory'],
            // Tables
            ['slug' => 'tables:read',   'name' => 'View Tables',   'group' => 'tables'],
            ['slug' => 'tables:create', 'name' => 'Create Table',  'group' => 'tables'],
            ['slug' => 'tables:update', 'name' => 'Update Table',  'group' => 'tables'],
            ['slug' => 'tables:delete', 'name' => 'Delete Table',  'group' => 'tables'],
            // Transactions
            ['slug' => 'transactions:read',   'name' => 'View Transactions', 'group' => 'transactions'],
            ['slug' => 'transactions:create', 'name' => 'Create Transaction','group' => 'transactions'],
            ['slug' => 'transactions:delete', 'name' => 'Delete Transaction','group' => 'transactions'],
            ['slug' => 'transactions:discount','name'=> 'Apply Discount',    'group' => 'transactions'],
            // Kitchen
            ['slug' => 'kitchen:read',   'name' => 'View Kitchen Orders', 'group' => 'kitchen'],
            ['slug' => 'kitchen:update', 'name' => 'Update Kitchen Status','group' => 'kitchen'],
            // Shifts
            ['slug' => 'shifts:read',   'name' => 'View Shifts',  'group' => 'shifts'],
            ['slug' => 'shifts:open',   'name' => 'Open Shift',   'group' => 'shifts'],
            ['slug' => 'shifts:close',  'name' => 'Close Shift',  'group' => 'shifts'],
            // Reports
            ['slug' => 'reports:operational','name' => 'Operational Reports','group' => 'reports'],
            ['slug' => 'reports:profit',     'name' => 'Profit Reports',     'group' => 'reports'],
            // Subscriptions
            ['slug' => 'subscriptions:manage','name' => 'Manage Subscription','group' => 'subscriptions'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }

        // ── Define roles and their permission slugs ────────────────────────────
        $rolePermissions = [
            'super_admin' => array_column($permissions, 'slug'), // all permissions
            'owner' => [
                'outlets:read', 'outlets:create', 'outlets:update', 'outlets:delete',
                'users:read', 'users:create', 'users:update', 'users:delete',
                'products:read', 'products:create', 'products:update', 'products:delete',
                'categories:read', 'categories:create', 'categories:update', 'categories:delete',
                'modifiers:read', 'modifiers:create', 'modifiers:update', 'modifiers:delete',
                'ingredients:read', 'ingredients:create', 'ingredients:update', 'ingredients:delete', 'ingredients:adjust',
                'recipes:manage',
                'tables:read', 'tables:create', 'tables:update', 'tables:delete',
                'transactions:read', 'transactions:create', 'transactions:delete', 'transactions:discount',
                'kitchen:read', 'kitchen:update',
                'shifts:read', 'shifts:open', 'shifts:close',
                'reports:operational', 'reports:profit',
                'subscriptions:manage',
            ],
            'admin' => [
                'products:read', 'products:create', 'products:update', 'products:delete',
                'categories:read', 'categories:create', 'categories:update', 'categories:delete',
                'modifiers:read', 'modifiers:create', 'modifiers:update', 'modifiers:delete',
                'ingredients:read', 'ingredients:create', 'ingredients:update', 'ingredients:delete', 'ingredients:adjust',
                'recipes:manage',
                'tables:read', 'tables:create', 'tables:update', 'tables:delete',
                'transactions:read', 'transactions:delete',
                'kitchen:read',
                'shifts:read',
                'reports:operational',
                'users:read',
            ],
            'cashier' => [
                'products:read',
                'categories:read',
                'modifiers:read',
                'tables:read', 'tables:update',
                'transactions:read', 'transactions:create', 'transactions:discount',
                'kitchen:read',
                'shifts:read', 'shifts:open', 'shifts:close',
            ],
            'kitchen' => [
                'kitchen:read', 'kitchen:update',
                'products:read',
            ],
        ];

        foreach ($rolePermissions as $roleSlug => $permSlugs) {
            $role = Role::firstOrCreate(
                ['slug' => $roleSlug],
                ['name' => ucfirst(str_replace('_', ' ', $roleSlug))]
            );

            $permIds = Permission::whereIn('slug', $permSlugs)->pluck('id');
            $role->permissions()->sync($permIds);
        }
    }
}
