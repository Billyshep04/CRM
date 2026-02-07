<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $adminRole = Role::query()->where('slug', 'admin')->first();
        $staffRole = Role::query()->where('slug', 'staff')->first();
        $customerRole = Role::query()->where('slug', 'customer')->first();

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );

        $staff = User::query()->firstOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff User',
                'password' => Hash::make('password'),
            ]
        );

        $customer = User::query()->firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Customer User',
                'password' => Hash::make('password'),
            ]
        );

        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        if ($staffRole) {
            $staff->roles()->syncWithoutDetaching([$staffRole->id]);
        }

        if ($customerRole) {
            $customer->roles()->syncWithoutDetaching([$customerRole->id]);
        }

        Customer::query()->firstOrCreate(
            ['user_id' => $customer->id],
            [
                'name' => 'Example Customer',
                'email' => $customer->email,
                'billing_address' => '123 Main Street' . PHP_EOL . 'Springfield, USA',
                'notes' => 'Seeded customer record for portal access.',
                'created_by_user_id' => $admin->id,
            ]
        );
    }
}
