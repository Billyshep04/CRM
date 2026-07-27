<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Http\Resources\JobResource;
use App\Http\Resources\ProposalResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\WebsiteResource;
use App\Jobs\SendProposalAcceptedNotification;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Proposal;
use App\Models\Subscription;
use App\Models\Website;
use App\Services\AdminMailSettings;
use App\Services\InvoiceJobStatusSyncService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceSubscriptionMonthSyncService;
use App\Services\ProposalPdfService;
use App\Services\RecurringInvoiceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PortalController extends Controller
{
    public function jobs(Request $request)
    {
        $customerIds = $this->resolveCustomerIds($request);

        $query = Job::query()
            ->whereIn('customer_id', $customerIds)
            ->when(
                Schema::hasColumn('jobs', 'archived_at'),
                static fn ($builder) => $builder->whereNull('archived_at')
            )
            ->latest();

        $perPage = $request->integer('per_page', 15);

        return JobResource::collection(
            $query->paginate($perPage)
        );
    }

    public function subscriptions(Request $request, RecurringInvoiceService $recurringInvoiceService)
    {
        $customerIds = $this->resolveCustomerIds($request);
        $this->processRecurringInvoices($recurringInvoiceService, $customerIds);

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

    public function invoices(Request $request, RecurringInvoiceService $recurringInvoiceService)
    {
        $customerIds = $this->resolveCustomerIds($request);
        $this->processRecurringInvoices($recurringInvoiceService, $customerIds);

        $query = Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->with(['lineItems', 'pdfFile'])
            ->latest();

        $query->filterByStatus($request->query('status'));

        $perPage = $request->integer('per_page', 15);

        return InvoiceResource::collection(
            $query->paginate($perPage)
        );
    }

    public function proposals(Request $request)
    {
        $perPage = $request->integer('per_page', 15);
        if (!$this->proposalsFeatureReady()) {
            $emptyPaginator = new LengthAwarePaginator(
                [],
                0,
                $perPage,
                1,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return ProposalResource::collection($emptyPaginator);
        }

        $customerIds = $this->resolveCustomerIds($request);

        $query = Proposal::query()
            ->whereIn('customer_id', $customerIds)
            ->with(['job', 'lineItems', 'pdfFile'])
            ->latest('id');

        $query->filterByStatus($request->query('status'));

        return ProposalResource::collection(
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

    public function proposal(Request $request, Proposal $proposal)
    {
        if (!$this->proposalsFeatureReady()) {
            abort(404);
        }

        $customerIds = $this->resolveCustomerIds($request);

        if (!in_array((int) $proposal->customer_id, $customerIds, true)) {
            abort(404);
        }

        return new ProposalResource($proposal->load(['job', 'lineItems', 'pdfFile']));
    }

    public function downloadInvoice(Request $request, Invoice $invoice, InvoicePdfService $pdfService)
    {
        $customerIds = $this->resolveCustomerIds($request);

        if (!in_array((int) $invoice->customer_id, $customerIds, true)) {
            abort(404);
        }

        $storedFile = $pdfService->generate($invoice);
        if ($invoice->pdf_file_id !== $storedFile->id) {
            $invoice->forceFill(['pdf_file_id' => $storedFile->id])->save();
        }
        $invoice->setRelation('pdfFile', $storedFile);

        return Storage::disk($invoice->pdfFile->disk)->download(
            $invoice->pdfFile->path,
            "Invoice-{$invoice->invoice_number}.pdf"
        );
    }

    public function downloadProposal(Request $request, Proposal $proposal, ProposalPdfService $pdfService)
    {
        if (!$this->proposalsFeatureReady()) {
            abort(404);
        }

        $customerIds = $this->resolveCustomerIds($request);

        if (!in_array((int) $proposal->customer_id, $customerIds, true)) {
            abort(404);
        }

        $proposal->loadMissing('pdfFile');

        if (!$proposal->pdfFile) {
            $storedFile = $pdfService->generate($proposal);
            $proposal->forceFill(['pdf_file_id' => $storedFile->id])->save();
            $proposal->setRelation('pdfFile', $storedFile);
        }

        return Storage::disk($proposal->pdfFile->disk)->download(
            $proposal->pdfFile->path,
            "Proposal-{$proposal->proposal_number}-v{$proposal->version}.pdf"
        );
    }

    public function updateInvoicePayment(
        Request $request,
        Invoice $invoice,
        InvoiceSubscriptionMonthSyncService $subscriptionMonthSync,
        InvoiceJobStatusSyncService $invoiceJobStatusSync
    )
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

        $loadedInvoice = $invoice->loadMissing('lineItems');
        $subscriptionMonthSync->syncFromInvoice($loadedInvoice, $validated['payment_status']);
        $invoiceJobStatusSync->syncFromInvoice($loadedInvoice, $validated['payment_status']);

        return new InvoiceResource($invoice->load(['lineItems', 'pdfFile']));
    }

    public function updateProposalStatus(Request $request, Proposal $proposal)
    {
        if (!$this->proposalsFeatureReady()) {
            throw ValidationException::withMessages([
                'proposal' => ['Proposal database tables are missing. Run database migrations, then retry.'],
            ]);
        }

        $customerIds = $this->resolveCustomerIds($request);

        if (!in_array((int) $proposal->customer_id, $customerIds, true)) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'declined', 'accepted', 'rejected'])],
        ]);

        $status = match ($validated['status']) {
            'accepted' => 'approved',
            'rejected' => 'declined',
            default => $validated['status'],
        };

        if ($status === 'approved') {
            $proposal->forceFill([
                'status' => 'approved',
                'accepted_at' => $proposal->accepted_at ?? now(),
                'rejected_at' => null,
                'locked_at' => $proposal->locked_at ?? now(),
            ])->save();

            $this->createJobFromApprovedProposal($proposal, $request->user()?->id);
            $this->sendProposalAcceptedNotificationNow($proposal);
        } else {
            $proposal->forceFill([
                'status' => 'declined',
                'accepted_at' => null,
                'rejected_at' => $proposal->rejected_at ?? now(),
                'locked_at' => $proposal->locked_at ?? now(),
            ])->save();
        }

        return new ProposalResource($proposal->load(['job', 'lineItems', 'pdfFile']));
    }

    public function support(Request $request, AdminMailSettings $mailSettings)
    {
        $validated = $request->validate([
            'problem' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'screenshot' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:8192'],
        ]);

        $customerIds = $this->resolveCustomerIds($request);
        $customer = Customer::query()->whereIn('id', $customerIds)->first();
        $user = $request->user();

        $requesterName = trim((string) ($customer?->name ?: $user?->name ?: 'Customer'));
        $requesterEmail = trim((string) ($customer?->email ?: $user?->email ?: ''));
        $targetEmail = 'info@web-stamp.co.uk';
        $problem = trim((string) $validated['problem']);
        $details = trim((string) $validated['message']);

        $subject = "Customer support request: {$problem}";
        $bodyLines = [
            "Problem: {$problem}",
            "From: {$requesterName}",
            "Email: {$requesterEmail}",
            "Customer ID(s): " . implode(', ', array_map(static fn (int $id): string => (string) $id, $customerIds)),
            'Submitted: ' . now()->toDateTimeString(),
            '',
            'Details:',
            $details,
        ];
        $body = implode("\n", $bodyLines);
        $screenshot = $request->file('screenshot');

        try {
            if ($mailSettings->smtp2goEnabled()) {
                $this->sendSupportViaSmtp2go(
                    $mailSettings,
                    $targetEmail,
                    $subject,
                    $body,
                    $requesterEmail,
                    $screenshot
                );
            } else {
                $this->sendSupportViaDefaultMailer(
                    $targetEmail,
                    $subject,
                    $body,
                    $requesterName,
                    $requesterEmail,
                    $screenshot
                );
            }
        } catch (Throwable $exception) {
            Log::error('Customer support email send failed', [
                'customer_ids' => $customerIds,
                'user_id' => $user?->id,
                'error' => $exception->getMessage(),
            ]);
            report($exception);

            throw ValidationException::withMessages([
                'support' => ['Unable to send support request right now. Please try again shortly.'],
            ]);
        }

        return response()->json([
            'message' => 'Support request sent.',
        ]);
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

    /**
     * @param  array<int>  $customerIds
     */
    private function processRecurringInvoices(
        RecurringInvoiceService $recurringInvoiceService,
        array $customerIds
    ): void {
        $autoSend = (bool) config('invoices.auto_send_recurring', true);
        $recurringInvoiceService->processDueSubscriptions(null, $autoSend, $customerIds);
    }

    private function sendSupportViaDefaultMailer(
        string $targetEmail,
        string $subject,
        string $body,
        string $requesterName,
        string $requesterEmail,
        ?UploadedFile $screenshot
    ): void {
        $fromAddress = trim((string) config('mail.from.address'));
        $fromName = trim((string) config('mail.from.name'));

        Mail::raw($body, function ($message) use (
            $targetEmail,
            $subject,
            $fromAddress,
            $fromName,
            $requesterName,
            $requesterEmail,
            $screenshot
        ): void {
            $message->to($targetEmail)->subject($subject);

            if ($fromAddress !== '') {
                $message->from($fromAddress, $fromName !== '' ? $fromName : null);
            }

            if ($requesterEmail !== '') {
                $message->replyTo($requesterEmail, $requesterName);
            }

            if ($screenshot) {
                $path = $screenshot->getRealPath();
                if ($path === false) {
                    throw new RuntimeException('Unable to read support attachment path.');
                }

                $message->attach(
                    $path,
                    [
                        'as' => $screenshot->getClientOriginalName() ?: 'support-attachment',
                        'mime' => $screenshot->getMimeType() ?: 'application/octet-stream',
                    ]
                );
            }
        });
    }

    private function sendSupportViaSmtp2go(
        AdminMailSettings $mailSettings,
        string $targetEmail,
        string $subject,
        string $body,
        string $requesterEmail,
        ?UploadedFile $screenshot
    ): void {
        $apiKey = $mailSettings->smtp2goApiKey();
        if ($apiKey === null || $apiKey === '') {
            throw new RuntimeException('SMTP2GO is enabled but no API key is configured.');
        }

        $fromAddress = trim((string) config('mail.from.address'));
        if ($fromAddress === '') {
            throw new RuntimeException('MAIL_FROM_ADDRESS is missing.');
        }

        $fromName = trim((string) config('mail.from.name'));
        $sender = $fromName !== '' ? "{$fromName} <{$fromAddress}>" : $fromAddress;

        $payload = [
            'api_key' => $apiKey,
            'sender' => $sender,
            'to' => [$targetEmail],
            'subject' => $subject,
            'text_body' => $body,
        ];

        if ($requesterEmail !== '') {
            $payload['reply_to'] = [$requesterEmail];
        }

        if ($screenshot) {
            $path = $screenshot->getRealPath();
            if ($path === false) {
                throw new RuntimeException('Unable to read support attachment path.');
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('Unable to read support attachment.');
            }

            $payload['attachments'] = [[
                'filename' => $screenshot->getClientOriginalName() ?: 'support-attachment',
                'fileblob' => base64_encode($contents),
                'mimetype' => $screenshot->getMimeType() ?: 'application/octet-stream',
            ]];
        }

        $response = Http::acceptJson()
            ->timeout(20)
            ->post('https://api.smtp2go.com/v3/email/send', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                sprintf('SMTP2GO request failed (%d): %s', $response->status(), $response->body())
            );
        }

        $failed = (int) data_get($response->json(), 'data.failed', 0);
        $succeeded = (int) data_get($response->json(), 'data.succeeded', 0);

        if ($failed > 0 || $succeeded < 1) {
            $failureMessage = data_get($response->json(), 'data.failures.0.error')
                ?: data_get($response->json(), 'data.failures.0.message')
                ?: 'Unknown SMTP2GO failure.';

            throw new RuntimeException((string) $failureMessage);
        }
    }

    private function sendProposalAcceptedNotificationNow(Proposal $proposal): void
    {
        try {
            SendProposalAcceptedNotification::dispatchSync($proposal->id);
        } catch (Throwable $exception) {
            Log::error('Proposal accepted notification email failed', [
                'proposal_id' => $proposal->id,
                'proposal_number' => $proposal->proposal_number,
                'customer_id' => $proposal->customer_id,
                'error' => $exception->getMessage(),
            ]);
            report($exception);

            throw ValidationException::withMessages([
                'status' => ['Proposal status was updated, but admin notification email could not be sent.'],
            ]);
        }
    }

    private function createJobFromApprovedProposal(Proposal $proposal, ?int $userId = null): void
    {
        if ($proposal->job_id) {
            return;
        }

        $answers = collect($proposal->form_answers ?? [])
            ->map(function (array $answer): string {
                $value = $answer['value'] ?? null;
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                }

                return ($answer['label'] ?? $answer['key'] ?? 'Question') . ': ' . ($value === null || $value === '' ? 'Not specified' : $value);
            })
            ->implode("\n");

        $job = Job::create([
            'customer_id' => $proposal->customer_id,
            'created_by_user_id' => $userId ?: $proposal->created_by_user_id,
            'description' => $proposal->title,
            'notes' => trim("Created automatically from approved proposal {$proposal->proposal_number} v{$proposal->version}.\n\nProposal type: {$proposal->proposal_type_label}\n\n{$answers}\n\n{$proposal->notes}"),
            'cost' => $proposal->total,
            'status' => 'open',
        ]);

        $proposal->forceFill(['job_id' => $job->id])->save();
    }

    private function proposalsFeatureReady(): bool
    {
        return Schema::hasTable('proposals') && Schema::hasTable('proposal_line_items');
    }
}
