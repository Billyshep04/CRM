<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Jobs\SendInvoiceEmail;
use App\Models\InvoiceLineItem;
use App\Models\Job;
use App\Models\Subscription;
use App\Services\InvoiceNumberGenerator;
use App\Services\InvoicePdfService;
use App\Services\RecurringInvoiceService;
use App\Services\InvoiceJobStatusSyncService;
use App\Services\InvoiceSubscriptionMonthSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceController extends Controller
{
    public function index(Request $request, RecurringInvoiceService $recurringInvoiceService)
    {
        $customerId = $request->query('customer_id');
        $this->processRecurringInvoices(
            $recurringInvoiceService,
            $customerId ? [(int) $customerId] : null
        );

        $query = Invoice::query()->with(['customer', 'lineItems', 'pdfFile'])->latest();

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $query->filterByStatus($request->query('status'));

        $perPage = $request->integer('per_page', 15);

        return InvoiceResource::collection(
            $query->paginate($perPage)
        );
    }

    public function store(
        Request $request,
        InvoiceNumberGenerator $numberGenerator,
        InvoiceSubscriptionMonthSyncService $subscriptionMonthSync,
        InvoiceJobStatusSyncService $invoiceJobStatusSync
    )
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_number' => ['nullable', 'string', 'max:64'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'status' => ['nullable', Rule::in(['draft', 'sent', 'paid', 'overdue'])],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string'],
            'line_items.*.quantity' => $this->quantityRules('required'),
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_items.*.billable_type' => ['nullable', Rule::in(['job', 'subscription'])],
            'line_items.*.billable_id' => ['nullable', 'integer'],
        ]);

        $lineItems = $validated['line_items'];

        $invoice = DB::transaction(function () use ($validated, $lineItems, $request, $numberGenerator): Invoice {
            $invoiceNumber = $validated['invoice_number'] ?? $numberGenerator->generate(
                (int) $validated['customer_id'],
                $validated['issue_date']
            );

            $subtotal = 0;
            foreach ($lineItems as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }

            $taxAmount = $validated['tax_amount'] ?? 0;
            $total = $subtotal + $taxAmount;

            $invoice = Invoice::create([
                'customer_id' => $validated['customer_id'],
                'created_by_user_id' => $request->user()?->id,
                'invoice_number' => $invoiceNumber,
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'status' => $validated['status'] ?? 'draft',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            $this->syncLineItems($invoice, $lineItems);

            return $invoice->load(['customer', 'lineItems', 'pdfFile']);
        });

        if ($invoice->status === 'sent' && !$invoice->sent_at) {
            $this->sendInvoiceEmailNow($invoice);
            $invoice->forceFill(['sent_at' => now()])->save();
        }

        if ($invoice->status === 'paid') {
            $invoice->forceFill([
                'paid_at' => $invoice->paid_at ?? now(),
            ])->save();
            $this->syncInvoiceLinkedPaymentStatus($invoice, 'paid', $subscriptionMonthSync, $invoiceJobStatusSync);
        }

        return new InvoiceResource($invoice);
    }

    public function show(Invoice $invoice)
    {
        return new InvoiceResource($invoice->load(['customer', 'lineItems', 'pdfFile']));
    }

    public function update(
        Request $request,
        Invoice $invoice,
        InvoiceSubscriptionMonthSyncService $subscriptionMonthSync,
        InvoiceJobStatusSyncService $invoiceJobStatusSync
    )
    {
        $validated = $request->validate([
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'invoice_number' => ['sometimes', 'string', 'max:64'],
            'issue_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(['draft', 'sent', 'paid', 'overdue'])],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items' => ['sometimes', 'array', 'min:1'],
            'line_items.*.description' => ['required_with:line_items', 'string'],
            'line_items.*.quantity' => $this->quantityRules('required_with:line_items'),
            'line_items.*.unit_price' => ['required_with:line_items', 'numeric', 'min:0'],
            'line_items.*.billable_type' => ['nullable', Rule::in(['job', 'subscription'])],
            'line_items.*.billable_id' => ['nullable', 'integer'],
        ]);

        $invoice = DB::transaction(function () use ($validated, $invoice): Invoice {
            $lineItems = $validated['line_items'] ?? null;
            $taxAmount = array_key_exists('tax_amount', $validated)
                ? $validated['tax_amount']
                : $invoice->tax_amount;

            if ($lineItems !== null) {
                $subtotal = 0;
                foreach ($lineItems as $item) {
                    $subtotal += $item['quantity'] * $item['unit_price'];
                }

                $total = $subtotal + $taxAmount;

                $invoice->update([
                    ...$validated,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);

                $this->syncLineItems($invoice, $lineItems, true);
            } else {
                $payload = $validated;
                if (array_key_exists('tax_amount', $validated)) {
                    $payload['total'] = $invoice->subtotal + $taxAmount;
                }

                $invoice->update($payload);
            }

            return $invoice->load(['customer', 'lineItems', 'pdfFile']);
        });

        if (($validated['status'] ?? null) === 'sent' && !$invoice->sent_at) {
            $this->sendInvoiceEmailNow($invoice);
            $invoice->forceFill(['sent_at' => now()])->save();
        }

        if ($invoice->status === 'paid') {
            $invoice->forceFill([
                'paid_at' => $invoice->paid_at ?? now(),
            ])->save();
            $this->syncInvoiceLinkedPaymentStatus($invoice, 'paid', $subscriptionMonthSync, $invoiceJobStatusSync);
        }

        return new InvoiceResource($invoice);
    }

    public function send(Invoice $invoice, InvoicePdfService $pdfService)
    {
        if (!$invoice->pdfFile) {
            $storedFile = $pdfService->generate($invoice);
            $invoice->forceFill(['pdf_file_id' => $storedFile->id])->save();
        }

        $this->sendInvoiceEmailNow($invoice);

        $invoice->forceFill([
            'status' => 'sent',
            'sent_at' => $invoice->sent_at ?? now(),
        ])->save();

        return new InvoiceResource($invoice->load(['customer', 'lineItems', 'pdfFile']));
    }

    public function download(Invoice $invoice, InvoicePdfService $pdfService)
    {
        if (!$invoice->pdfFile) {
            $storedFile = $pdfService->generate($invoice);
            $invoice->forceFill(['pdf_file_id' => $storedFile->id])->save();
            $invoice->setRelation('pdfFile', $storedFile);
        }

        return Storage::disk($invoice->pdfFile->disk)->download(
            $invoice->pdfFile->path,
            "Invoice-{$invoice->invoice_number}.pdf"
        );
    }

    public function updatePaymentStatus(
        Request $request,
        Invoice $invoice,
        InvoiceSubscriptionMonthSyncService $subscriptionMonthSync,
        InvoiceJobStatusSyncService $invoiceJobStatusSync
    )
    {
        $validated = $request->validate([
            'payment_status' => ['required', Rule::in(['paid', 'unpaid'])],
        ]);

        if ($validated['payment_status'] === 'paid') {
            $invoice->forceFill([
                'status' => 'paid',
                'paid_at' => $invoice->paid_at ?? now(),
            ])->save();
        } else {
            $fallbackStatus = $invoice->due_date && $invoice->due_date->isPast()
                ? 'overdue'
                : 'sent';

            $invoice->forceFill([
                'status' => $fallbackStatus,
                'paid_at' => null,
            ])->save();
        }

        $loadedInvoice = $invoice->loadMissing('lineItems');
        $this->syncInvoiceLinkedPaymentStatus($loadedInvoice, $validated['payment_status'], $subscriptionMonthSync, $invoiceJobStatusSync);

        return new InvoiceResource($invoice->load(['customer', 'lineItems', 'pdfFile']));
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted.']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    private function syncLineItems(Invoice $invoice, array $lineItems, bool $replace = false): void
    {
        if ($replace) {
            InvoiceLineItem::query()->where('invoice_id', $invoice->id)->delete();
        }

        foreach ($lineItems as $item) {
            $billableType = $this->resolveBillableType($item['billable_type'] ?? null);
            $billableId = $item['billable_id'] ?? null;
            $description = trim((string) ($item['description'] ?? ''));

            // Manual line items should not fail if a billable ID was typed accidentally.
            if (!$billableType) {
                $billableId = null;
            } elseif (empty($billableId)) {
                throw ValidationException::withMessages([
                    'line_items' => ['Billable ID is required when billable type is set.'],
                ]);
            }

            if ($billableType && $billableId) {
                $billable = $billableType::query()
                    ->where('id', $billableId)
                    ->where('customer_id', $invoice->customer_id)
                    ->firstOrFail();

                if ($billable instanceof Job) {
                    $billable->update([
                        'status' => 'invoiced',
                        'invoiced_at' => $billable->invoiced_at ?? now(),
                    ]);

                    // Keep invoice description aligned to the source job description.
                    $description = trim((string) $billable->description);
                } elseif ($billable instanceof Subscription && $description === '') {
                    $description = trim((string) $billable->description);
                }
            }

            if ($description === '') {
                throw ValidationException::withMessages([
                    'line_items' => ['Line item description is required.'],
                ]);
            }

            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'billable_type' => $billableType,
                'billable_id' => $billableId,
                'description' => $description,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $item['quantity'] * $item['unit_price'],
            ]);
        }
    }

    private function resolveBillableType(?string $type): ?string
    {
        return match ($type) {
            'job' => Job::class,
            'subscription' => Subscription::class,
            default => null,
        };
    }

    /**
     * @return array<int, mixed>
     */
    private function quantityRules(string $requiredRule): array
    {
        return [
            $requiredRule,
            'numeric',
            'min:0.5',
            static function (string $attribute, mixed $value, \Closure $fail): void {
                $quantity = (float) $value;
                $scaled = $quantity * 2;
                $isHalfStep = abs($scaled - round($scaled)) < 0.00001;

                if (!$isHalfStep) {
                    $fail("The {$attribute} field must be a whole number or end in .5.");
                }
            },
        ];
    }

    /**
     * @param  array<int>|null  $customerIds
     */
    private function processRecurringInvoices(
        RecurringInvoiceService $recurringInvoiceService,
        ?array $customerIds = null
    ): void {
        $autoSend = (bool) config('invoices.auto_send_recurring', true);
        $recurringInvoiceService->processDueSubscriptions(null, $autoSend, $customerIds);
    }

    private function sendInvoiceEmailNow(Invoice $invoice): void
    {
        try {
            SendInvoiceEmail::dispatchSync($invoice->id);
        } catch (Throwable $exception) {
            Log::error('Invoice email send failed', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'error' => $exception->getMessage(),
            ]);
            report($exception);

            throw ValidationException::withMessages([
                'send' => ['Invoice email could not be sent. Check mail settings in .env (MAIL_*).'],
            ]);
        }
    }

    private function syncInvoiceLinkedPaymentStatus(
        Invoice $invoice,
        string $paymentStatus,
        InvoiceSubscriptionMonthSyncService $subscriptionMonthSync,
        InvoiceJobStatusSyncService $invoiceJobStatusSync
    ): void {
        $loadedInvoice = $invoice->loadMissing('lineItems');
        $subscriptionMonthSync->syncFromInvoice($loadedInvoice, $paymentStatus);
        $invoiceJobStatusSync->syncFromInvoice($loadedInvoice, $paymentStatus);
    }

}
