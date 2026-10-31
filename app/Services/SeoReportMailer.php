<?php

namespace App\Services;

use App\Mail\SeoReportMailable;
use App\Models\SeoReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use RuntimeException;

class SeoReportMailer
{
    public function __construct(
        private readonly SeoReportPdfService $pdfService,
        private readonly AdminMailSettings $mailSettings
    ) {
    }

    /**
     * Email the report PDF to the client contact and return the address it went to.
     */
    public function send(SeoReport $report): string
    {
        $report->loadMissing('customer');
        $targetEmail = $report->recipientEmail();
        if ($targetEmail === '') {
            throw new RuntimeException('Customer email is missing.');
        }

        $monthLabel = $report->report_month->format('F Y');
        $subject = "Your {$monthLabel} SEO report for {$report->website_domain}";
        $contactName = trim((string) ($report->data['contact_name'] ?? ''));
        $viewData = [
            'report' => $report,
            'monthLabel' => $monthLabel,
            'greetingName' => $contactName !== '' ? strtok($contactName, ' ') : ($report->customer?->name ?: 'there'),
            'summary' => trim((string) ($report->data['summary'] ?? '')),
        ];
        $pdf = $this->pdfService->render($report)->output();

        if ($this->mailSettings->smtp2goEnabled()) {
            $this->sendViaSmtp2go($report, $targetEmail, $subject, View::make('emails.seo-report', $viewData)->render(), $pdf);

            return $targetEmail;
        }

        Mail::to($targetEmail)->send(new SeoReportMailable($report, $subject, $viewData, $pdf));

        return $targetEmail;
    }

    private function sendViaSmtp2go(SeoReport $report, string $targetEmail, string $subject, string $htmlBody, string $pdf): void
    {
        $apiKey = $this->mailSettings->smtp2goApiKey();
        if ($apiKey === null || $apiKey === '') {
            throw new RuntimeException('SMTP2GO is enabled but no API key is configured.');
        }

        $fromAddress = trim((string) config('mail.from.address'));
        if ($fromAddress === '') {
            throw new RuntimeException('MAIL_FROM_ADDRESS is missing.');
        }

        $fromName = trim((string) config('mail.from.name'));
        $sender = $fromName !== '' ? "{$fromName} <{$fromAddress}>" : $fromAddress;

        $response = Http::acceptJson()->timeout(30)->post('https://api.smtp2go.com/v3/email/send', [
            'api_key' => $apiKey,
            'sender' => $sender,
            'to' => [$targetEmail],
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => "{$subject}\nPlease see the attached PDF.",
            'attachments' => [[
                'filename' => $report->downloadFileName(),
                'fileblob' => base64_encode($pdf),
                'mimetype' => 'application/pdf',
            ]],
        ]);

        $failed = (int) data_get($response->json(), 'data.failed', 0);
        $succeeded = (int) data_get($response->json(), 'data.succeeded', 0);
        if ($response->failed() || $failed > 0 || $succeeded < 1) {
            throw new RuntimeException('SMTP2GO could not send the SEO report.');
        }
    }
}
