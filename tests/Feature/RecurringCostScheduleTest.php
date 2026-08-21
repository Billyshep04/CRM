<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringCostScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_price_change_preserves_previous_months_and_updates_future_months(): void
    {
        Carbon::setTestNow('2026-08-20 10:00:00');
        $admin = User::factory()->create();
        $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $admin->roles()->attach($role);
        Sanctum::actingAs($admin);

        $id = $this->postJson('/api/recurring-costs', [
            'description' => 'Software subscription', 'amount' => 20, 'starts_on' => '2026-07-15', 'frequency' => 'monthly',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/recurring-costs/{$id}", [
            'description' => 'Software subscription', 'amount' => 30, 'effective_from' => '2026-09-01', 'frequency' => 'monthly',
        ])->assertOk();

        $this->getJson('/api/costs/monthly?month=2026-08')->assertOk()
            ->assertJsonPath('recurring_total', 20)
            ->assertJsonPath('entries.0.entry_type', 'recurring');
        $this->getJson('/api/costs/monthly?month=2026-09')->assertOk()
            ->assertJsonPath('recurring_total', 30);

        $months = collect($this->getJson('/api/admin/stats/monthly-finance')->assertOk()->json('months'));
        $this->assertEquals(20, $months->firstWhere('month_start', '2026-08-01')['costs_total']);
    }

    public function test_customer_cannot_manage_recurring_costs(): void
    {
        $customer = User::factory()->create();
        $role = Role::query()->firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);
        $customer->roles()->attach($role);
        Sanctum::actingAs($customer);

        $this->getJson('/api/recurring-costs')->assertForbidden();
        $this->getJson('/api/costs/months')->assertForbidden();
    }
}
