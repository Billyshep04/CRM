<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Http\Resources\JobResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\WebsiteResource;
use App\Models\Customer;
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
        $customerIds = $this->resolveCustomerIds($request);

        $query = Job::query()
            ->whereIn('customer_id', $customerIds)
            ->latest();

        $perPage = $request->integer('per_page', 15);

        return JobResource::collection(
            $query->paginate($perPage)
        );
    }

    public function subscriptions(Request $request)
    {
        $customerIds = $this->resolveCustomerIds($request);

        $query = Subscription::query()
            ->whereIn('customer_id', $customerIds)
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
        $customerIds = $this->resolveCustomerIds($request);

        $query = Invoice::query()
            ->whereIn('customer_id', $customerIds)
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
        $customerIds = $this->resolveCustomerIds($request);

        $query = Website::query()
            ->whereIn('customer_id', $customerIds)
            ->latest();

        $perPage = $request->integer('per_page', 25);

        return WebsiteResource::collection(
            $query->paginate($perPage)
        );
    }

    public function invoice(Request $request, Invoice $invoice)
    {
        $customerIds = $this->resolveCustomerIds($request);

        if (!in_array((int) $invoice->customer_id, $customerIds, true)) {
            abort(404);
        }

        return new InvoiceResource($invoice->load(['lineItems', 'pdfFile']));
    }

    public function downloadInvoice(Request $request, Invoice $invoice)
    {
        $customerIds = $this->resolveCustomerIds($request);

        if (!in_array((int) $invoice->customer_id, $customerIds, true)) {
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
        $customerIds = $this->resolveCustomerIds($request);

        if (!in_array((int) $invoice->customer_id, $customerIds, true)) {
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

    /**
     * @return array<int>
     */
    private function resolveCustomerIds(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            abort(404, 'Customer profile not found.');
        }

        $customerIds = Customer::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        // Fallback for legacy records that may not yet be linked by user_id.
        if ($customerIds === [] && $user->email) {
            $customerIds = Customer::query()
                ->where('email', $user->email)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        if ($customerIds === []) {
            abort(404, 'Customer profile not found.');
        }

        return $customerIds;
    }
}
