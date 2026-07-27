<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_phone_can_be_created_updated_and_searched(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/customers', [
            'name' => 'Phone Test Customer',
            'email' => 'phone-test@example.com',
            'phone' => '01603 123 456',
            'billing_address' => '1 Test Street, Norwich',
        ])->assertOk()->assertJsonPath('data.phone', '01603 123 456');

        $customerId = $response->json('data.id');
        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'phone' => '01603 123 456',
        ]);

        $this->actingAs($admin)->putJson("/api/customers/{$customerId}", [
            'phone' => '+44 1603 654321',
        ])->assertOk()->assertJsonPath('data.phone', '+44 1603 654321');

        $this->actingAs($admin)->getJson('/api/customers?search=654321')
            ->assertOk()
            ->assertJsonPath('data.0.id', $customerId);

        $portalUser = User::query()->findOrFail($response->json('data.user_id'));
        $this->actingAs($portalUser)->putJson('/api/account/profile', [
            'name' => 'Phone Test Customer',
            'email' => 'phone-test@example.com',
            'phone' => '07700 900123',
            'billing_address' => '1 Test Street, Norwich',
        ])->assertOk()->assertJsonPath('user.customer_profile.phone', '07700 900123');

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'phone' => '07700 900123',
        ]);
    }

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        return $user;
    }
}
