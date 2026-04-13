<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceStatusFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_overdue_filter_includes_unpaid_past_due_invoices(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-13 10:00:00'));

        $customer = $this->createCustomer();
        $overdueSent = $this->createInvoice($customer->id, 'sent', '2026-03-15');
        $overdueDraft = $this->createInvoice($customer->id, 'draft', '2026-03-20');
        $this->createInvoice($customer->id, 'paid', '2026-03-10');
        $this->createInvoice($customer->id, 'sent', '2026-04-20');

        $ids = Invoice::query()
            ->filterByStatus('overdue')
            ->pluck('id')
            ->all();

        $this->assertContains($overdueSent->id, $ids);
        $this->assertContains($overdueDraft->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_sent_filter_excludes_past_due_invoices(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-13 10:00:00'));

        $customer = $this->createCustomer();
        $futureSent = $this->createInvoice($customer->id, 'sent', '2026-04-20');
        $this->createInvoice($customer->id, 'sent', '2026-04-01');

        $ids = Invoice::query()
            ->filterByStatus('sent')
            ->pluck('id')
            ->all();

        $this->assertSame([$futureSent->id], $ids);
    }

    public function test_effective_status_marks_unpaid_past_due_as_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-13 10:00:00'));

        $customer = $this->createCustomer();
        $invoice = $this->createInvoice($customer->id, 'sent', '2026-03-15');
        $paidInvoice = $this->createInvoice($customer->id, 'paid', '2026-03-15');

        $this->assertSame('overdue', $invoice->fresh()->effectiveStatus());
        $this->assertSame('paid', $paidInvoice->fresh()->effectiveStatus());
    }

    private function createCustomer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Test Customer',
            'email' => 'customer-' . uniqid('', true) . '@example.test',
            'billing_address' => '1 Billing Street',
        ]);
    }

    private function createInvoice(int $customerId, string $status, string $dueDate): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customerId,
            'invoice_number' => 'INV-' . strtoupper(substr(str_replace('.', '', uniqid('', true)), 0, 12)),
            'issue_date' => '2026-03-01',
            'due_date' => $dueDate,
            'status' => $status,
            'subtotal' => 15.00,
            'tax_amount' => 0,
            'total' => 15.00,
        ]);
    }
}

