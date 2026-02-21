<?php

namespace App\Jobs;

use App\Mail\InvoiceMailable;
use App\Models\Invoice;
use App\Services\AdminMailSettings;
use App\Services\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use RuntimeException;

class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $invoiceId)
    {
    }

    public function handle(InvoicePdfService $pdfService, AdminMailSettings $mailSettings): void
    {
        $invoice = Invoice::query()
            ->with(['customer', 'pdfFile', 'lineItems'])
            ->findOrFail($this->invoiceId);

        if (!$invoice->customer) {
            return;
        }

        $storedFile = $pdfService->generate($invoice);

        if ($invoice->pdf_file_id !== $storedFile->id) {
            $invoice->forceFill(['pdf_file_id' => $storedFile->id])->save();
        }
        $invoice->setRelation('pdfFile', $storedFile);

        if ($mailSettings->smtp2goEnabled()) {
            $apiKey = $mailSettings->smtp2goApiKey();
            if ($apiKey === null || $apiKey === '') {
                throw new RuntimeException('SMTP2GO is enabled but no API key is configured.');
            }

            $this->sendViaSmtp2go($invoice, $apiKey);

            return;
        }

        Mail::to($invoice->customer->email)
            ->send(new InvoiceMailable($invoice));
    }

    private function sendViaSmtp2go(Invoice $invoice, string $apiKey): void
    {
        $fromAddress = trim((string) config('mail.from.address'));
        if ($fromAddress === '') {
            throw new RuntimeException('MAIL_FROM_ADDRESS is missing.');
        }

        $toAddress = trim((string) $invoice->customer?->email);
        if ($toAddress === '') {
            throw new RuntimeException('Customer email is missing.');
        }

        $fromName = trim((string) config('mail.from.name'));
        $sender = $fromName !== '' ? "{$fromName} <{$fromAddress}>" : $fromAddress;

        $htmlBody = View::make('emails.invoice', [
            'invoice' => $invoice,
            'customer' => $invoice->customer,
        ])->render();

        $payload = [
            'api_key' => $apiKey,
            'sender' => $sender,
            'to' => [$toAddress],
            'subject' => "Invoice {$invoice->invoice_number}",
            'html_body' => $htmlBody,
            'text_body' => "Invoice {$invoice->invoice_number}\nPlease see the attached PDF.",
        ];

        if (
            $invoice->pdfFile
            && is_string($invoice->pdfFile->disk)
            && is_string($invoice->pdfFile->path)
            && $invoice->pdfFile->disk !== ''
            && $invoice->pdfFile->path !== ''
            && Storage::disk($invoice->pdfFile->disk)->exists($invoice->pdfFile->path)
        ) {
            $payload['attachments'] = [[
                'filename' => "Invoice-{$invoice->invoice_number}.pdf",
                'fileblob' => base64_encode(Storage::disk($invoice->pdfFile->disk)->get($invoice->pdfFile->path)),
                'mimetype' => 'application/pdf',
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
