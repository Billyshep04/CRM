<?php

namespace App\Jobs;

use App\Mail\OpportunityFollowUpReminderMailable;
use App\Models\CrmTask;
use App\Models\User;
use App\Services\AdminMailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use RuntimeException;

class SendOpportunityFollowUpReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $taskId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('opportunity-reminder:'.$this->taskId))->expireAfter(120)];
    }

    public function handle(AdminMailSettings $settings): void
    {
        $task = CrmTask::query()->with(['assignedTo', 'revenueOpportunity.customer'])->findOrFail($this->taskId);
        if ($task->reminder_sent_at || $task->status === 'completed' || ! $task->revenue_opportunity_id) {
            return;
        }

        $recipients = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->pluck('email')->filter()->unique()->values()->all();
        if ($recipients === []) {
            $recipients = [(string) config('agency-os.admin_email', 'info@web-stamp.co.uk')];
        }

        if ($settings->smtp2goEnabled()) {
            $key = $settings->smtp2goApiKey();
            if (! $key) {
                throw new RuntimeException('SMTP2GO is enabled but no API key is configured.');
            }
            $this->sendViaSmtp2go($task, $recipients, $key);
        } else {
            Mail::to($recipients)->send(new OpportunityFollowUpReminderMailable($task));
        }
        $task->update(['reminder_sent_at' => now()]);
    }

    private function sendViaSmtp2go(CrmTask $task, array $recipients, string $key): void
    {
        $fromAddress = trim((string) config('mail.from.address'));
        if ($fromAddress === '') {
            throw new RuntimeException('MAIL_FROM_ADDRESS is missing.');
        }
        $fromName = trim((string) config('mail.from.name'));
        $sender = $fromName !== '' ? "{$fromName} <{$fromAddress}>" : $fromAddress;
        $response = Http::acceptJson()->timeout(20)->post('https://api.smtp2go.com/v3/email/send', [
            'api_key' => $key, 'sender' => $sender, 'to' => $recipients,
            'subject' => 'Follow-up reminder: '.$task->revenueOpportunity?->title,
            'html_body' => View::make('emails.opportunity-follow-up-reminder', ['task' => $task])->render(),
            'text_body' => "Follow up with {$task->revenueOpportunity?->customer?->name} about {$task->revenueOpportunity?->title}.\n\nNotes: {$task->description}",
        ]);
        if ($response->failed() || (int) data_get($response->json(), 'data.failed', 0) > 0) {
            throw new RuntimeException('SMTP2GO follow-up reminder failed: '.$response->body());
        }
    }
}
