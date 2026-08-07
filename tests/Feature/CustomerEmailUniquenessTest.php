<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerEmailUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_shown_a_clear_error_when_customer_email_already_exists(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->userWithRole('admin');

        $existingCustomer = Customer::query()->create([
            'name' => 'Existing Customer',
            'email' => 'existing@example.com',
            'billing_address' => '1 Existing Street',
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->postJson('/api/customers', [
            'name' => 'Duplicate Customer',
            'email' => 'EXISTING@example.com',
            'billing_address' => '2 Duplicate Street',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'That email address already exists. Please use a different email address.');

        $customerToUpdate = Customer::query()->create([
            'name' => 'Customer To Update',
            'email' => 'update@example.com',
            'billing_address' => '3 Update Street',
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->putJson("/api/customers/{$customerToUpdate->id}", [
            'email' => $existingCustomer->email,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame('update@example.com', $customerToUpdate->fresh()->email);
    }

    public function test_customer_can_keep_their_current_portal_login_email(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->userWithRole('admin');
        $portalUser = $this->userWithRole('customer', 'portal@example.com');
        $customer = Customer::query()->create([
            'name' => 'Portal Customer',
            'email' => $portalUser->email,
            'billing_address' => '4 Portal Street',
            'user_id' => $portalUser->id,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->putJson("/api/customers/{$customer->id}", [
            'name' => 'Updated Portal Customer',
            'email' => $portalUser->email,
        ])->assertOk();
    }

    private function userWithRole(string $role, ?string $email = null): User
    {
        $user = User::factory()->create($email ? ['email' => $email] : []);
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
