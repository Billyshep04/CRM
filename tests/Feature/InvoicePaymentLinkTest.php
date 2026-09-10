<?php

namespace Tests\Feature;

use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceMailable;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AdminMailSettings;
use App\Services\InvoicePdfService;
use App\Services\RecurringInvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoicePaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_invoice_can_be_created_without_or_with_a_trimmed_https_payment_link(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $without = $this->actingAs($admin)->postJson('/api/invoices', $this->payload($customer))
            ->assertCreated()
            ->assertJsonPath('data.payment_link', null);

        $link = 'https://pay.example/checkout/invoice-2';
        $with = $this->actingAs($admin)->postJson('/api/invoices', [
            ...$this->payload($customer),
            'payment_link' => "  {$link}  ",
        ])->assertCreated()->assertJsonPath('data.payment_link', $link);

        $this->assertNull(Invoice::findOrFail($without->json('data.id'))->payment_link);
        $this->assertSame($link, Invoice::findOrFail($with->json('data.id'))->payment_link);
    }

    public function test_invalid_and_unsafe_payment_links_are_rejected(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        foreach (['not a URL', 'javascript:alert(1)', 'data:text/html,test', 'file:///tmp/payment'] as $link) {
            $this->actingAs($admin)->postJson('/api/invoices', [
                ...$this->payload($customer),
                'payment_link' => $link,
            ])->assertUnprocessable()->assertJsonValidationErrors('payment_link');
        }
    }

    public function test_payment_link_can_be_updated_and_removed(): void
    {
        $admin = $this->admin();
        $invoice = $this->invoice($this->customer(), 'https://pay.example/original');

        $this->actingAs($admin)->putJson("/api/invoices/{$invoice->id}", [
            'payment_link' => ' https://pay.example/replacement ',
        ])->assertOk()->assertJsonPath('data.payment_link', 'https://pay.example/replacement');

        $this->actingAs($admin)->putJson("/api/invoices/{$invoice->id}", [
            'payment_link' => '   ',
        ])->assertOk()->assertJsonPath('data.payment_link', null);

        $this->assertNull($invoice->fresh()->payment_link);
    }

    public function test_invoice_email_places_an_escaped_payment_cta_near_the_top_only_when_present(): void
    {
        $link = 'https://pay.example/checkout?invoice=1&customer=2';
        $invoice = $this->invoice($this->customer(), $link)->load(['customer', 'lineItems', 'pdfFile']);

        $html = (new InvoiceMailable($invoice))->render();
        $text = View::make('emails.invoice-text', ['invoice' => $invoice, 'customer' => $invoice->customer])->render();

        $this->assertStringContainsString('Pay this invoice online', $html);
        $this->assertStringContainsString('>Pay invoice</a>', $html);
        $this->assertStringContainsString('href="https://pay.example/checkout?invoice=1&amp;customer=2"', $html);
        $this->assertStringNotContainsString('invoice=1&customer=2"', $html);
        $this->assertLessThan(strpos($html, 'Amount Due'), strpos($html, 'Pay this invoice online'));
        $this->assertStringContainsString($link, $text);

        $without = $this->invoice($this->customer(), null, 'NO-PAYMENT-LINK')->load(['customer', 'lineItems', 'pdfFile']);
        $withoutHtml = (new InvoiceMailable($without))->render();
        $this->assertStringNotContainsString('Pay this invoice online', $withoutHtml);
        $this->assertStringNotContainsString('Pay invoice</a>', $withoutHtml);
    }

    public function test_payment_link_is_not_rendered_in_printable_or_generated_pdf_output(): void
    {
        Storage::fake('private');
        $link = 'https://pay.example/never-print-this-value';
        $invoice = $this->invoice($this->customer(), $link)->load(['customer', 'lineItems.billable']);

        $printable = View::make('pdf.invoice', [
            'invoice' => $invoice,
            'customer' => $invoice->customer,
            'payment_details' => ['account_name' => 'WebStamp', 'sort_code' => '00-00-00', 'account_number' => '00000000'],
            'invoice_logo_data_uri' => null,
            'invoice_stamp_data_uri' => null,
        ])->render();
        $stored = app(InvoicePdfService::class)->generate($invoice);
        $pdf = Storage::disk('private')->get($stored->path);

        $this->assertStringNotContainsString($link, $printable);
        $this->assertStringNotContainsString($link, $pdf);
        $this->assertStringNotContainsString('payment_link', file_get_contents(resource_path('views/pdf/invoice.blade.php')));
    }

    public function test_smtp2go_resend_uses_the_invoices_current_payment_link_in_html_and_plain_text(): void
    {
        Storage::fake('private');
        Http::fake([
            'https://api.smtp2go.com/v3/email/send' => Http::response(['data' => ['succeeded' => 1, 'failed' => 0]]),
        ]);
        $invoice = $this->invoice($this->customer(), 'https://pay.example/expired');
        $invoice->update(['payment_link' => 'https://pay.example/current?invoice=1&attempt=2']);
        $settings = new class extends AdminMailSettings
        {
            public function smtp2goEnabled(): bool { return true; }
            public function smtp2goApiKey(): ?string { return 'smtp2go-test-key'; }
        };

        (new SendInvoiceEmail($invoice->id))->handle(app(InvoicePdfService::class), $settings);

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://api.smtp2go.com/v3/email/send') return false;
            $html = (string) $request['html_body'];
            $text = (string) $request['text_body'];

            return str_contains($html, 'Pay this invoice online')
                && str_contains($html, 'href="https://pay.example/current?invoice=1&amp;attempt=2"')
                && str_contains($text, 'https://pay.example/current?invoice=1&attempt=2')
                && ! str_contains($html, 'https://pay.example/expired')
                && ! str_contains($text, 'https://pay.example/expired');
        });
    }

    public function test_customer_portal_does_not_expose_payment_link_but_staff_api_does(): void
    {
        $admin = $this->admin();
        $customerUser = User::factory()->create();
        $customerUser->roles()->attach(Role::query()->where('slug', 'customer')->firstOrFail());
        $customer = $this->customer(['user_id' => $customerUser->id]);
        $invoice = $this->invoice($customer, 'https://pay.example/private-link');

        $this->actingAs($admin)->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()->assertJsonPath('data.payment_link', 'https://pay.example/private-link');

        Sanctum::actingAs($customerUser);
        $portal = $this->getJson("/api/portal/invoices/{$invoice->id}")->assertOk();
        $this->assertArrayNotHasKey('payment_link', $portal->json('data'));
    }

    public function test_recurring_invoice_generation_does_not_copy_a_one_off_payment_link(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));
        $customer = $this->customer();
        $this->invoice($customer, 'https://pay.example/one-off');
        Subscription::query()->create([
            'customer_id' => $customer->id,
            'description' => 'Managed hosting',
            'monthly_cost' => 25,
            'billing_frequency' => 'monthly',
            'start_date' => '2026-09-01',
            'next_invoice_date' => '2026-09-01',
            'status' => 'active',
        ]);

        app(RecurringInvoiceService::class)->processDueSubscriptions(null, false, [$customer->id]);

        $generated = Invoice::query()->where('customer_id', $customer->id)->whereDate('issue_date', '2026-09-01')->firstOrFail();
        $this->assertNull($generated->payment_link);
    }

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        return $user;
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::query()->create([
            'name' => 'Payment Link Customer',
            'email' => fake()->unique()->safeEmail(),
            'billing_address' => '1 Billing Street',
            ...$attributes,
        ]);
    }

    private function payload(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'issue_date' => '2026-09-10',
            'due_date' => '2026-09-24',
            'status' => 'draft',
            'tax_amount' => 0,
            'line_items' => [[
                'description' => 'Website services',
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ];
    }

    private function invoice(Customer $customer, ?string $paymentLink, string $number = 'PAY-LINK-001'): Invoice
    {
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number.'-'.$customer->id,
            'issue_date' => '2026-09-10',
            'due_date' => '2026-09-24',
            'status' => 'draft',
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'payment_link' => $paymentLink,
        ]);
        InvoiceLineItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Website services',
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        return $invoice;
    }
}
