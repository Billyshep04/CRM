<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoices_can_be_archived_filtered_and_unarchived_without_data_loss(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());
        $customer = Customer::query()->create([
            'name' => 'Invoice Archive Customer',
            'email' => 'invoice-archive@example.test',
            'billing_address' => '1 Test Street',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $admin->id,
            'invoice_number' => 'ARCHIVE-INV-001',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-15',
            'status' => 'paid',
            'subtotal' => 125,
            'tax_amount' => 25,
            'total' => 150,
            'paid_at' => '2026-07-10 09:30:00',
        ]);

        $this->actingAs($admin)->patchJson("/api/invoices/{$invoice->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_archived', true);

        $archived = $invoice->fresh();
        $this->assertNotNull($archived->archived_at);
        $this->assertSame('paid', $archived->status);
        $this->assertSame(150.0, (float) $archived->total);

        $this->actingAs($admin)->getJson('/api/invoices')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($admin)->getJson('/api/invoices?archived=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoice->id);

        $this->actingAs($admin)->patchJson("/api/invoices/{$invoice->id}/unarchive")
            ->assertOk()
            ->assertJsonPath('data.is_archived', false);

        $this->assertNull($invoice->fresh()->archived_at);
        $this->actingAs($admin)->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoice->id);
    }
}
