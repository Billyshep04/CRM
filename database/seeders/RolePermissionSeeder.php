<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed default roles and permissions.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Full system access.',
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Manage customers, jobs, subscriptions, and invoices.',
            ],
            [
                'name' => 'Customer',
                'slug' => 'customer',
                'description' => 'Portal access to own data only.',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::query()->firstOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );
        }

        $permissions = [
            [
                'name' => 'Manage users',
                'slug' => 'manage_users',
                'description' => 'Create and manage users.',
            ],
            [
                'name' => 'Manage roles',
                'slug' => 'manage_roles',
                'description' => 'Assign roles and permissions.',
            ],
            [
                'name' => 'Manage customers',
                'slug' => 'manage_customers',
                'description' => 'Create and manage customers.',
            ],
            [
                'name' => 'Manage jobs',
                'slug' => 'manage_jobs',
                'description' => 'Create and manage jobs.',
            ],
            [
                'name' => 'Manage subscriptions',
                'slug' => 'manage_subscriptions',
                'description' => 'Create and manage subscriptions.',
            ],
            [
                'name' => 'Manage invoices',
                'slug' => 'manage_invoices',
                'description' => 'Create and manage invoices.',
            ],
            [
                'name' => 'View portal',
                'slug' => 'view_portal',
                'description' => 'View customer portal data.',
            ],
        ];

        foreach ($permissions as $permissionData) {
            Permission::query()->firstOrCreate(
                ['slug' => $permissionData['slug']],
                $permissionData
            );
        }

        $adminRole = Role::query()->where('slug', 'admin')->first();
        $staffRole = Role::query()->where('slug', 'staff')->first();
        $customerRole = Role::query()->where('slug', 'customer')->first();

        $allPermissions = Permission::query()->pluck('id');
        $staffPermissions = Permission::query()
            ->whereIn('slug', [
                'manage_customers',
                'manage_jobs',
                'manage_subscriptions',
                'manage_invoices',
            ])
            ->pluck('id');
        $customerPermissions = Permission::query()
            ->whereIn('slug', ['view_portal'])
            ->pluck('id');

        if ($adminRole) {
            $adminRole->permissions()->sync($allPermissions);
        }

        if ($staffRole) {
            $staffRole->permissions()->sync($staffPermissions);
        }

        if ($customerRole) {
            $customerRole->permissions()->sync($customerPermissions);
        }
    }
}
