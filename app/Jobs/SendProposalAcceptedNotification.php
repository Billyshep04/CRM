<?php

namespace App\Jobs;

use App\Mail\ProposalAcceptedAdminMailable;
use App\Models\Proposal;
use App\Services\AdminMailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use RuntimeException;

class SendProposalAcceptedNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $proposalId)
    {
    }

    public function handle(AdminMailSettings $mailSettings): void
    {
        $proposal = Proposal::query()
            ->with(['customer', 'job', 'lineItems'])
            ->findOrFail($this->proposalId);

        $targetEmail = 'info@web-stamp.co.uk';

        if ($mailSettings->smtp2goEnabled()) {
            $apiKey = $mailSettings->smtp2goApiKey();
            if ($apiKey === null || $apiKey === '') {
                throw new RuntimeException('SMTP2GO is enabled but no API key is configured.');
            }

            $this->sendViaSmtp2go($proposal, $targetEmail, $apiKey);

            return;
        }

        Mail::to($targetEmail)->send(new ProposalAcceptedAdminMailable($proposal));
    }

    private function sendViaSmtp2go(Proposal $proposal, string $targetEmail, string $apiKey): void
    {
        $fromAddress = trim((string) config('mail.from.address'));
        if ($fromAddress === '') {
            throw new RuntimeException('MAIL_FROM_ADDRESS is missing.');
        }

        $fromName = trim((string) config('mail.from.name'));
        $sender = $fromName !== '' ? "{$fromName} <{$fromAddress}>" : $fromAddress;

        $htmlBody = View::make('emails.proposal-accepted-admin', [
            'proposal' => $proposal,
            'customer' => $proposal->customer,
            'job' => $proposal->job,
        ])->render();

        $payload = [
            'api_key' => $apiKey,
            'sender' => $sender,
            'to' => [$targetEmail],
            'subject' => "Proposal accepted: {$proposal->proposal_number} (v{$proposal->version})",
            'html_body' => $htmlBody,
            'text_body' => "Proposal accepted: {$proposal->proposal_number} (v{$proposal->version})",
        ];

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
