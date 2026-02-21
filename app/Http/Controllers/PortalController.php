<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Http\Resources\JobResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\WebsiteResource;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Subscription;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    public function jobs(Request $request)
    {
        $customerId = $this->resolveCustomerId($request);

        $query = Job::query()
            ->where('customer_id', $customerId)
            ->latest();

        $perPage = $request->integer('per_page', 15);

        return JobResource::collection(
            $query->paginate($perPage)
        );
    }

    public function subscriptions(Request $request)
    {
        $customerId = $this->resolveCustomerId($request);

        $query = Subscription::query()
            ->where('customer_id', $customerId)
            ->latest();

        $status = $request->query('status', 'active');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $perPage = $request->integer('per_page', 15);

        return SubscriptionResource::collection(
            $query->paginate($perPage)
        );
    }

    public function invoices(Request $request)
    {
        $customerId = $this->resolveCustomerId($request);

        $query = Invoice::query()
            ->where('customer_id', $customerId)
            ->with(['lineItems', 'pdfFile'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = $request->integer('per_page', 15);

        return InvoiceResource::collection(
            $query->paginate($perPage)
        );
    }

    public function websites(Request $request)
    {
        $customerId = $this->resolveCustomerId($request);

        $query = Website::query()
            ->where('customer_id', $customerId)
            ->latest();

        $perPage = $request->integer('per_page', 25);

        return WebsiteResource::collection(
            $query->paginate($perPage)
        );
    }

    public function invoice(Request $request, Invoice $invoice)
    {
        $customerId = $this->resolveCustomerId($request);

        if ($invoice->customer_id !== $customerId) {
            abort(404);
        }

        return new InvoiceResource($invoice->load(['lineItems', 'pdfFile']));
    }

    public function downloadInvoice(Request $request, Invoice $invoice)
    {
        $customerId = $this->resolveCustomerId($request);

        if ($invoice->customer_id !== $customerId) {
            abort(404);
        }

        $invoice->loadMissing('pdfFile');

        if (!$invoice->pdfFile) {
            abort(404, 'Invoice PDF not available.');
        }

        return Storage::disk($invoice->pdfFile->disk)->download(
            $invoice->pdfFile->path,
            "Invoice-{$invoice->invoice_number}.pdf"
        );
    }

    public function updateInvoicePayment(Request $request, Invoice $invoice)
    {
        $customerId = $this->resolveCustomerId($request);

        if ($invoice->customer_id !== $customerId) {
            abort(404);
        }

        $validated = $request->validate([
            'payment_status' => ['required', 'in:paid,unpaid'],
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

        return new InvoiceResource($invoice->load(['lineItems', 'pdfFile']));
    }

    private function resolveCustomerId(Request $request): int
    {
        $customer = $request->user()?->customerProfile;
        if (!$customer) {
            abort(404, 'Customer profile not found.');
        }

        return $customer->id;
    }
}
