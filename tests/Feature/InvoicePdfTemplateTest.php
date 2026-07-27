<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Job;
use App\Models\StoredFile;
use App\Models\Subscription;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoicePdfTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_invoice_pdf_is_refreshed_to_the_new_template(): void
    {
        Storage::fake('private');
        [$invoice] = $this->invoiceWithLineItem(Job::class, 'Website configuration');

        Storage::disk('private')->put('invoices/legacy.pdf', 'old invoice template');
        $legacyFile = StoredFile::query()->create([
            'disk' => 'private',
            'path' => 'invoices/legacy.pdf',
            'original_name' => 'legacy.pdf',
            'mime_type' => 'application/pdf',
            'size' => 20,
            'category' => 'invoice_pdf',
            'checksum' => hash('sha256', 'old invoice template'),
            'is_private' => true,
            'owner_type' => Invoice::class,
            'owner_id' => $invoice->id,
            'metadata' => ['invoice_template_version' => 1],
        ]);
        $invoice->forceFill(['pdf_file_id' => $legacyFile->id])->save();

        $rendered = app(InvoicePdfService::class)->generate($invoice->fresh());

        $this->assertSame($legacyFile->id, $rendered->id);
        $this->assertSame(6, (int) data_get($rendered->metadata, 'invoice_template_version'));
        $this->assertStringStartsWith('%PDF', Storage::disk('private')->get($rendered->path));
    }

    public function test_job_and_subscription_invoices_render_with_the_new_single_page_design(): void
    {
        Storage::fake('private');

        foreach ([Job::class, Subscription::class] as $billableType) {
            [$invoice] = $this->invoiceWithLineItem($billableType, 'Managed service');
            $rendered = app(InvoicePdfService::class)->generate($invoice);
            $contents = Storage::disk('private')->get($rendered->path);

            $this->assertStringStartsWith('%PDF', $contents);
            $this->assertStringContainsString('/Count 1', $contents);
            $this->assertSame(6, (int) data_get($rendered->metadata, 'invoice_template_version'));
        }
    }

    /**
     * @return array{Invoice, Job|Subscription}
     */
    private function invoiceWithLineItem(string $billableType, string $description): array
    {
        $customer = Customer::query()->create([
            'name' => 'Jason Hayes',
            'email' => 'jason.hayes@example.test',
            'phone' => '07700 900123',
            'billing_address' => '55 Beards Road, Fremington, Devon, EX31 2PG',
        ]);

        $billable = $billableType === Job::class
            ? Job::query()->create([
                'customer_id' => $customer->id,
                'description' => $description,
                'notes' => 'Project delivery note.',
                'cost' => 20,
                'status' => 'invoiced',
            ])
            : Subscription::query()->create([
                'customer_id' => $customer->id,
                'description' => $description,
                'monthly_cost' => 20,
                'billing_frequency' => 'monthly',
                'start_date' => '2026-07-01',
                'next_invoice_date' => '2026-08-01',
                'status' => 'active',
            ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'JH-8-230726-'.$customer->id,
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'status' => 'draft',
            'subtotal' => 20,
            'tax_amount' => 0,
            'total' => 20,
        ]);

        InvoiceLineItem::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => $billableType,
            'billable_id' => $billable->id,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => 20,
            'total' => 20,
        ]);

        return [$invoice, $billable];
    }
}
