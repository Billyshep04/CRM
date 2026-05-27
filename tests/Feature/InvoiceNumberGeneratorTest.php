<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_customer_initial_sequence_and_issue_date_number(): void
    {
        $customer = $this->createCustomer('James Sherlock');

        for ($i = 1; $i <= 4; $i++) {
            $this->createInvoice($customer->id, "OLD-{$i}");
        }

        $invoiceNumber = app(InvoiceNumberGenerator::class)->generate(
            $customer->id,
            '2026-01-01'
        );

        $this->assertSame('JS-5-010126', $invoiceNumber);
    }

    public function test_manual_invoice_creation_uses_customer_issue_date_number(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $user->roles()->attach($role);

        $customer = $this->createCustomer('James Sherlock');

        for ($i = 1; $i <= 4; $i++) {
            $this->createInvoice($customer->id, "OLD-{$i}");
        }

        $response = $this->actingAs($user)->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-15',
            'status' => 'draft',
            'tax_amount' => 0,
            'line_items' => [
                [
                    'description' => 'Website support',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.invoice_number', 'JS-5-010126');

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'invoice_number' => 'JS-5-010126',
        ]);
    }

    private function createCustomer(string $name): Customer
    {
        return Customer::query()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '-' . uniqid() . '@example.test',
            'billing_address' => '1 Billing Street',
        ]);
    }

    private function createInvoice(int $customerId, string $invoiceNumber): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customerId,
            'invoice_number' => $invoiceNumber,
            'issue_date' => '2025-12-01',
            'due_date' => '2025-12-15',
            'status' => 'paid',
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
        ]);
    }
}
