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
use App\Services\AdminMailSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
}
