<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffOperationalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_access_operational_areas_but_not_finance_staff_or_admin_settings(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $staff = $this->userWithRole('staff');
        $customer = Customer::query()->create([
            'name' => 'Staff Access Customer',
            'email' => 'staff-access@example.test',
            'billing_address' => '1 Access Street',
            'created_by_user_id' => $staff->id,
        ]);

        $this->actingAs($staff)->getJson('/api/customers')->assertOk();
        $this->actingAs($staff)->getJson('/api/jobs')->assertOk();
        $this->actingAs($staff)->getJson('/api/subscriptions')->assertOk();
        $this->actingAs($staff)->getJson('/api/costs')->assertOk();
        $this->actingAs($staff)->getJson('/api/proposals')->assertOk();
        $this->actingAs($staff)->getJson('/api/invoices')->assertOk();
        $this->actingAs($staff)->getJson('/api/lead-discovery')->assertOk();
        $this->actingAs($staff)->getJson('/api/revenue-opportunities')->assertOk();
        $this->actingAs($staff)->getJson("/api/customers/{$customer->id}/forms")->assertOk();
        $this->actingAs($staff)->getJson('/api/admin/staff-users')->assertOk();

        $this->actingAs($staff)->postJson('/api/tasks', [
            'assigned_to_user_id' => $staff->id,
            'title' => 'Staff-created task',
            'status' => 'pending',
        ])->assertCreated();

        $this->actingAs($staff)->getJson('/api/admin/stats/monthly-finance')->assertForbidden();
        $this->actingAs($staff)->getJson('/api/admin/staff-task-summary')->assertForbidden();
        $this->actingAs($staff)->postJson('/api/admin/staff-users', [])->assertForbidden();
        $this->actingAs($staff)->getJson('/api/admin/organisation-settings')->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
