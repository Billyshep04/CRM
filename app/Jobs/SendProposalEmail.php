<?php

namespace App\Jobs;

use App\Mail\ProposalMailable;
use App\Models\Proposal;
use App\Services\AdminMailSettings;
use App\Services\ProposalPdfService;
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

class SendProposalEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $proposalId)
    {
    }

    public function handle(ProposalPdfService $pdfService, AdminMailSettings $mailSettings): void
    {
        $proposal = Proposal::query()
            ->with(['customer', 'job', 'lineItems', 'pdfFile'])
            ->findOrFail($this->proposalId);

        if (!$proposal->customer) {
            return;
        }

        $storedFile = $pdfService->generate($proposal);

        if ($proposal->pdf_file_id !== $storedFile->id) {
            $proposal->forceFill(['pdf_file_id' => $storedFile->id])->save();
        }
        $proposal->setRelation('pdfFile', $storedFile);

        if ($mailSettings->smtp2goEnabled()) {
            $apiKey = $mailSettings->smtp2goApiKey();
            if ($apiKey === null || $apiKey === '') {
                throw new RuntimeException('SMTP2GO is enabled but no API key is configured.');
            }

            $this->sendViaSmtp2go($proposal, $apiKey);

            return;
        }

        Mail::to($proposal->customer->email)
            ->send(new ProposalMailable($proposal));
    }

    private function sendViaSmtp2go(Proposal $proposal, string $apiKey): void
    {
        $fromAddress = trim((string) config('mail.from.address'));
        if ($fromAddress === '') {
            throw new RuntimeException('MAIL_FROM_ADDRESS is missing.');
        }

        $toAddress = trim((string) $proposal->customer?->email);
        if ($toAddress === '') {
            throw new RuntimeException('Customer email is missing.');
        }

        $fromName = trim((string) config('mail.from.name'));
        $sender = $fromName !== '' ? "{$fromName} <{$fromAddress}>" : $fromAddress;

        $htmlBody = View::make('emails.proposal', [
            'proposal' => $proposal,
            'customer' => $proposal->customer,
            'job' => $proposal->job,
        ])->render();

        $payload = [
            'api_key' => $apiKey,
            'sender' => $sender,
            'to' => [$toAddress],
            'subject' => "Proposal {$proposal->proposal_number} (v{$proposal->version})",
            'html_body' => $htmlBody,
            'text_body' => "Proposal {$proposal->proposal_number} (v{$proposal->version})\nPlease see the attached PDF.",
        ];

        if (
            $proposal->pdfFile
            && is_string($proposal->pdfFile->disk)
            && is_string($proposal->pdfFile->path)
            && $proposal->pdfFile->disk !== ''
            && $proposal->pdfFile->path !== ''
            && Storage::disk($proposal->pdfFile->disk)->exists($proposal->pdfFile->path)
        ) {
            $payload['attachments'] = [[
                'filename' => "Proposal-{$proposal->proposal_number}-v{$proposal->version}.pdf",
                'fileblob' => base64_encode(Storage::disk($proposal->pdfFile->disk)->get($proposal->pdfFile->path)),
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
