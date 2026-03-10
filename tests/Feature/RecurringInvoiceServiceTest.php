<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Subscription;
use App\Services\RecurringInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_missed_monthly_invoices_and_advances_next_invoice_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));

        $customer = $this->createCustomer('monthly@example.test');

        $subscription = Subscription::query()->create([
            'customer_id' => $customer->id,
            'description' => 'Managed hosting',
            'monthly_cost' => 15.00,
            'billing_frequency' => 'monthly',
            'start_date' => '2026-02-01',
            'next_invoice_date' => '2026-03-01',
            'status' => 'active',
        ]);

        $result = app(RecurringInvoiceService::class)
            ->processDueSubscriptions(null, false, [$customer->id]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['failed']);

        $invoice = Invoice::query()
            ->where('customer_id', $customer->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame('2026-03-01', $invoice->issue_date?->toDateString());

        $subscription->refresh();
        $this->assertSame('2026-04-01', $subscription->next_invoice_date?->toDateString());
    }

    public function test_it_only_processes_due_subscriptions_for_selected_customers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));

        $customerA = $this->createCustomer('customer-a@example.test');
        $customerB = $this->createCustomer('customer-b@example.test');

        Subscription::query()->create([
            'customer_id' => $customerA->id,
            'description' => 'Hosting A',
            'monthly_cost' => 20.00,
            'billing_frequency' => 'monthly',
            'start_date' => '2026-01-01',
            'next_invoice_date' => '2026-03-01',
            'status' => 'active',
        ]);

        $subscriptionB = Subscription::query()->create([
            'customer_id' => $customerB->id,
            'description' => 'Hosting B',
            'monthly_cost' => 25.00,
            'billing_frequency' => 'monthly',
            'start_date' => '2026-01-01',
            'next_invoice_date' => '2026-03-01',
            'status' => 'active',
        ]);

        $result = app(RecurringInvoiceService::class)
            ->processDueSubscriptions(null, false, [$customerA->id]);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', ['customer_id' => $customerA->id]);
        $this->assertDatabaseMissing('invoices', ['customer_id' => $customerB->id]);

        $subscriptionB->refresh();
        $this->assertSame('2026-03-01', $subscriptionB->next_invoice_date?->toDateString());
    }

    public function test_it_does_not_duplicate_existing_subscription_invoice_for_same_issue_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));

        $customer = $this->createCustomer('dedupe@example.test');

        $subscription = Subscription::query()->create([
            'customer_id' => $customer->id,
            'description' => 'Managed hosting',
            'monthly_cost' => 15.00,
            'billing_frequency' => 'monthly',
            'start_date' => '2026-02-01',
            'next_invoice_date' => '2026-03-01',
            'status' => 'active',
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-20260301-EXISTS',
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-15',
            'status' => 'sent',
            'subtotal' => 15.00,
            'tax_amount' => 0,
            'total' => 15.00,
        ]);

        InvoiceLineItem::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => Subscription::class,
            'billable_id' => $subscription->id,
            'description' => 'Managed hosting',
            'quantity' => 1,
            'unit_price' => 15.00,
            'total' => 15.00,
        ]);

        $result = app(RecurringInvoiceService::class)
            ->processDueSubscriptions(null, false, [$customer->id]);

        $this->assertSame(0, $result['created']);
        $this->assertDatabaseCount('invoices', 1);

        $subscription->refresh();
        $this->assertSame('2026-04-01', $subscription->next_invoice_date?->toDateString());
    }

    private function createCustomer(string $email): Customer
    {
        return Customer::query()->create([
            'name' => 'Test Customer',
            'email' => $email,
            'billing_address' => '1 Billing Street',
        ]);
    }
}
