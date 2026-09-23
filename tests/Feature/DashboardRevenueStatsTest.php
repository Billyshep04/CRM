<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardRevenueStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_total_counts_invoices_paid_this_month_regardless_of_job_status(): void
    {
        $staff = User::factory()->create();
        $this->assignRole($staff, 'staff');
        Sanctum::actingAs($staff);

        $customer = $this->customer();

        // Paid this month: counts.
        $this->invoice($customer, 500.00, 'paid', now());
        // Completed job with no paid invoice at all: must NOT count.
        Job::query()->create([
            'customer_id' => $customer->id,
            'description' => 'Unbilled work',
            'cost' => 900.00,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        // Paid last month: must NOT count in this month's total.
        $this->invoice($customer, 300.00, 'paid', now()->subMonthNoOverflow());
        // Sent but not yet paid: must NOT count.
        $this->invoice($customer, 750.00, 'sent', null);

        $response = $this->getJson('/api/stats/revenue')->assertOk();

        $this->assertSame(500.0, (float) $response->json('paid_invoices_total'));
        // The pre-existing job-based fields keep working exactly as before this change.
        $this->assertSame(900.0, (float) $response->json('completed_jobs_total'));
    }

    public function test_monthly_finance_revenue_also_uses_invoices_paid_in_that_month(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');
        Sanctum::actingAs($admin);

        $customer = $this->customer();
        $thisMonthStart = now()->startOfMonth();

        $this->invoice($customer, 500.00, 'paid', $thisMonthStart->copy()->addDays(2));
        Job::query()->create([
            'customer_id' => $customer->id,
            'description' => 'Unbilled work',
            'cost' => 900.00,
            'status' => 'completed',
            'completed_at' => $thisMonthStart->copy()->addDays(3),
        ]);

        $response = $this->getJson('/api/admin/stats/monthly-finance')->assertOk();
        $months = collect($response->json('months'));
        $currentMonth = $months->firstWhere('month_start', $thisMonthStart->toDateString());

        $this->assertNotNull($currentMonth);
        $this->assertSame(500.0, (float) $currentMonth['revenue_total']);
    }

    private function customer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Revenue Test Customer',
            'email' => 'revenue-test@example.test',
            'billing_address' => '1 Billing Street',
            'created_by_user_id' => null,
        ]);
    }

    private function invoice(Customer $customer, float $total, string $status, $paidAt): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-' . strtoupper(substr(str_replace('.', '', uniqid('', true)), 0, 12)),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => $status,
            'subtotal' => $total,
            'tax_amount' => 0,
            'total' => $total,
            'paid_at' => $paidAt,
        ]);
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
