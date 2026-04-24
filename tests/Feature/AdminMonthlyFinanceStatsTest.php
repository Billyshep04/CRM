<?php

namespace Tests\Feature;

use App\Models\Cost;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionMonth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMonthlyFinanceStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_fetch_monthly_finance_from_march_to_current_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-16 10:00:00'));

        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');
        Sanctum::actingAs($admin);

        $customer = Customer::query()->create([
            'name' => 'Tommy May',
            'email' => 'tommy@example.test',
            'billing_address' => '1 Billing Street',
        ]);

        Job::query()->create([
            'customer_id' => $customer->id,
            'description' => 'March completed work',
            'cost' => 120.00,
            'status' => 'completed',
            'completed_at' => '2026-03-10 09:00:00',
        ]);

        Job::query()->create([
            'customer_id' => $customer->id,
            'description' => 'April completed work',
            'cost' => 40.00,
            'status' => 'completed',
            'completed_at' => '2026-04-05 09:00:00',
        ]);

        $subscription = Subscription::query()->create([
            'customer_id' => $customer->id,
            'description' => 'Managed hosting',
            'monthly_cost' => 80.00,
            'billing_frequency' => 'monthly',
            'start_date' => '2026-02-01',
            'status' => 'active',
        ]);

        SubscriptionMonth::query()->create([
            'subscription_id' => $subscription->id,
            'month_start' => '2026-03-01',
            'subscription_status' => 'active',
            'payment_status' => 'paid',
        ]);

        Cost::query()->create([
            'description' => 'March one-off cost',
            'amount' => 50.00,
            'incurred_on' => '2026-03-05',
            'is_recurring' => false,
        ]);

        Cost::query()->create([
            'description' => 'Monthly software license',
            'amount' => 15.00,
            'incurred_on' => '2026-03-01',
            'is_recurring' => true,
            'recurring_frequency' => 'monthly',
        ]);

        $response = $this->getJson('/api/admin/stats/monthly-finance');

        $response->assertOk();
        $response->assertJsonPath('start_month', '2026-03-01');
        $response->assertJsonPath('end_month', '2026-04-01');
        $response->assertJsonPath('selected_month', '2026-04-01');

        $months = collect($response->json('months'));
        $this->assertCount(2, $months);

        $march = $months->firstWhere('month_start', '2026-03-01');
        $this->assertNotNull($march);
        $this->assertSame('March 2026', $march['label']);
        $this->assertEquals(200.0, $march['revenue_total']);
        $this->assertEquals(65.0, $march['costs_total']);
        $this->assertEquals(135.0, $march['profit_total']);
        $this->assertEquals(27.0, $march['tax_total']);

        $april = $months->firstWhere('month_start', '2026-04-01');
        $this->assertNotNull($april);
        $this->assertEquals(40.0, $april['revenue_total']);
        $this->assertEquals(15.0, $april['costs_total']);
        $this->assertEquals(25.0, $april['profit_total']);
        $this->assertEquals(5.0, $april['tax_total']);
    }

    public function test_non_admin_cannot_fetch_monthly_finance_stats(): void
    {
        $staffUser = User::factory()->create();
        $this->assignRole($staffUser, 'staff');
        Sanctum::actingAs($staffUser);

        $this->getJson('/api/admin/stats/monthly-finance')->assertForbidden();
    }

    private function assignRole(User $user, string $roleSlug): void
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst($roleSlug)]
        );

        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
