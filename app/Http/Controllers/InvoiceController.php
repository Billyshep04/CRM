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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::query()->with(['customer', 'lineItems', 'pdfFile'])->latest();

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = $request->integer('per_page', 15);

        return InvoiceResource::collection(
            $query->paginate($perPage)
        );
    }

    public function store(Request $request, InvoiceNumberGenerator $numberGenerator)
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
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_items.*.billable_type' => ['nullable', Rule::in(['job', 'subscription'])],
            'line_items.*.billable_id' => ['nullable', 'integer'],
        ]);

        $lineItems = $validated['line_items'];

        $invoice = DB::transaction(function () use ($validated, $lineItems, $request, $numberGenerator): Invoice {
            $invoiceNumber = $validated['invoice_number'] ?? $numberGenerator->generate();

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

        return new InvoiceResource($invoice);
    }

    public function show(Invoice $invoice)
    {
        return new InvoiceResource($invoice->load(['customer', 'lineItems', 'pdfFile']));
    }

    public function update(Request $request, Invoice $invoice)
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
            'line_items.*.quantity' => ['required_with:line_items', 'integer', 'min:1'],
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
                }
            }

            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'billable_type' => $billableType,
                'billable_id' => $billableId,
                'description' => $item['description'],
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

}
