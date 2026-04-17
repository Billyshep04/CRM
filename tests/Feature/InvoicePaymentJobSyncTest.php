<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Job;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoicePaymentJobSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_payment_marked_paid_sets_linked_job_to_completed(): void
    {
        $staffUser = User::factory()->create();
        $this->assignRole($staffUser, 'staff');
        Sanctum::actingAs($staffUser);

        [$customer, $job, $invoice] = $this->createInvoiceWithLinkedJob();

        $this->patchJson("/api/invoices/{$invoice->id}/payment", [
            'payment_status' => 'paid',
        ])->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'status' => 'completed',
        ]);
        $this->assertNotNull($job->fresh()->completed_at);
    }

    public function test_customer_portal_payment_marked_paid_sets_linked_job_to_completed(): void
    {
        $customerUser = User::factory()->create();
        $this->assignRole($customerUser, 'customer');

        [$customer, $job, $invoice] = $this->createInvoiceWithLinkedJob($customerUser);
        Sanctum::actingAs($customerUser);

        $this->patchJson("/api/portal/invoices/{$invoice->id}/payment", [
            'payment_status' => 'paid',
        ])->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'status' => 'completed',
        ]);
        $this->assertNotNull($job->fresh()->completed_at);
    }

    /**
     * @return array{Customer, Job, Invoice}
     */
    private function createInvoiceWithLinkedJob(?User $customerUser = null): array
    {
        $customer = Customer::query()->create([
            'name' => 'Tommy May',
            'email' => 'tommy@example.test',
            'billing_address' => '1 Billing Street',
            'user_id' => $customerUser?->id,
            'created_by_user_id' => null,
        ]);

        $job = Job::query()->create([
            'customer_id' => $customer->id,
            'description' => 'On-site support',
            'cost' => 450.00,
            'status' => 'invoiced',
            'invoiced_at' => now()->subDay(),
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-' . strtoupper(substr(str_replace('.', '', uniqid('', true)), 0, 12)),
            'issue_date' => now()->subDays(14)->toDateString(),
            'due_date' => now()->subDays(1)->toDateString(),
            'status' => 'sent',
            'subtotal' => 450.00,
            'tax_amount' => 0,
            'total' => 450.00,
        ]);

        InvoiceLineItem::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => Job::class,
            'billable_id' => $job->id,
            'description' => 'On-site support',
            'quantity' => 1,
            'unit_price' => 450.00,
            'total' => 450.00,
        ]);

        return [$customer, $job, $invoice];
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

