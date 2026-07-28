<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaidViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_and_paid_invoice_views_are_split_by_payment_status(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());
        $customer = Customer::query()->create([
            'name' => 'Payment View Customer',
            'email' => 'payment-view@example.test',
            'billing_address' => '1 Test Street',
        ]);

        $currentInvoice = $this->createInvoice($customer, $admin, 'CURRENT-001', 'sent');
        $paidInvoice = $this->createInvoice($customer, $admin, 'PAID-001', 'paid');
        $paidInvoice->forceFill(['archived_at' => now(), 'paid_at' => now()])->save();

        $this->actingAs($admin)->getJson('/api/invoices?payment_view=current')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $currentInvoice->id);

        $this->actingAs($admin)->getJson('/api/invoices?payment_view=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paidInvoice->id);
    }

    private function createInvoice(Customer $customer, User $admin, string $number, string $status): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $admin->id,
            'invoice_number' => $number,
            'issue_date' => '2026-07-01',
            'due_date' => '2026-08-01',
            'status' => $status,
            'subtotal' => 100,
            'tax_amount' => 20,
            'total' => 120,
        ]);
    }
}
