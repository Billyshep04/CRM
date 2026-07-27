<?php

namespace App\Services;

use App\Mail\CustomerFormCompletedAdminMailable;
use App\Mail\CustomerFormRequestedMailable;
use App\Models\CustomerFormRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use RuntimeException;

class CustomerFormNotificationService
{
    public function __construct(private readonly AdminMailSettings $mailSettings)
    {
    }

    public function notifyCustomer(CustomerFormRequest $formRequest): void
    {
        $formRequest->loadMissing('customer');
        $targetEmail = trim((string) $formRequest->customer?->email);
        if ($targetEmail === '') {
            throw new RuntimeException('Customer email is missing.');
        }

        $portalUrl = rtrim((string) config('app.url'), '/');
        $subject = "New form to complete: {$formRequest->template_name}";
        $viewData = compact('formRequest', 'portalUrl') + ['customer' => $formRequest->customer];

        if ($this->mailSettings->smtp2goEnabled()) {
            $this->sendViaSmtp2go(
                $targetEmail,
                $subject,
                View::make('emails.customer-form-requested', $viewData)->render(),
                "A new {$formRequest->template_name} form is waiting in your WebStamp customer portal: {$portalUrl}"
            );

            return;
        }

        Mail::to($targetEmail)->send(new CustomerFormRequestedMailable($formRequest, $portalUrl));
    }

    public function notifyAdmin(CustomerFormRequest $formRequest): void
    {
        $formRequest->loadMissing('customer');
        $targetEmail = 'info@web-stamp.co.uk';
        $crmUrl = rtrim((string) config('app.url'), '/');
        $subject = "Customer form completed: {$formRequest->template_name}";
        $viewData = compact('formRequest', 'crmUrl') + ['customer' => $formRequest->customer];

        if ($this->mailSettings->smtp2goEnabled()) {
            $customerName = $formRequest->customer?->name ?: 'Customer';
            $this->sendViaSmtp2go(
                $targetEmail,
                $subject,
                View::make('emails.customer-form-completed-admin', $viewData)->render(),
                "{$customerName} completed {$formRequest->template_name}. Review it in the CRM: {$crmUrl}"
            );

            return;
        }

        Mail::to($targetEmail)->send(new CustomerFormCompletedAdminMailable($formRequest, $crmUrl));
    }

    private function sendViaSmtp2go(string $targetEmail, string $subject, string $htmlBody, string $textBody): void
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

        $response = Http::acceptJson()->timeout(20)->post('https://api.smtp2go.com/v3/email/send', [
            'api_key' => $apiKey,
            'sender' => $sender,
            'to' => [$targetEmail],
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody,
        ]);

        $failed = (int) data_get($response->json(), 'data.failed', 0);
        $succeeded = (int) data_get($response->json(), 'data.succeeded', 0);
        if ($response->failed() || $failed > 0 || $succeeded < 1) {
            throw new RuntimeException('SMTP2GO could not send the customer form notification.');
        }
    }
}
