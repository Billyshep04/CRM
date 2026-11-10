<?php

namespace App\Services\EmailMarketing;

use App\Jobs\SendEmailCampaignBatch;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailSubscriber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sends a campaign to its lists: snapshot the recipients, then send in rate-limited
 * batches from the queue. Suppression is re-checked for every person just before sending.
 */
class CampaignSender
{
    public function __construct(
        private readonly CampaignMailer $mailer,
        private readonly SuppressionService $suppressions,
    ) {
    }

    public function start(EmailCampaign $campaign, ?User $by = null): EmailCampaign
    {
        DB::transaction(function () use ($campaign): void {
            $campaign = EmailCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if (!$campaign->isEditable()) {
                throw ValidationException::withMessages(['campaign' => 'This campaign has already been sent.']);
            }
            if ($problems = CampaignChecks::blockingProblems($campaign)) {
                throw ValidationException::withMessages(['campaign' => $problems[0]]);
            }

            $now = now();
            Recipients::query($campaign->lists()->pluck('email_lists.id')->all())
                ->select(['id', 'email', 'first_name', 'last_name'])
                ->chunkById(500, function ($subscribers) use ($campaign, $now): void {
                    EmailCampaignRecipient::insertOrIgnore($subscribers->map(fn (EmailSubscriber $subscriber) => [
                        'email_campaign_id' => $campaign->id,
                        'email_subscriber_id' => $subscriber->id,
                        'email' => $subscriber->email,
                        'first_name' => $subscriber->first_name,
                        'last_name' => $subscriber->last_name,
                        'status' => EmailCampaignRecipient::STATUS_PENDING,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                });

            $count = $campaign->recipients()->count();
            if ($count === 0) {
                throw ValidationException::withMessages(['campaign' => 'Nobody on the chosen lists can be emailed (they have all unsubscribed or bounced).']);
            }

            $campaign->update([
                'status' => EmailCampaign::STATUS_SENDING,
                'last_error' => null,
                'started_at' => $now,
                'recipients_count' => $count,
            ]);
        });

        SendEmailCampaignBatch::dispatch($campaign->id);

        return $campaign->fresh();
    }

    /**
     * Books a campaign to send later. It's checked now and again at send time, and the
     * recipients are worked out when it actually sends.
     */
    public function schedule(EmailCampaign $campaign, \DateTimeInterface $at): EmailCampaign
    {
        if (!$campaign->isEditable()) {
            throw ValidationException::withMessages(['campaign' => 'This campaign has already been sent.']);
        }
        if ($problems = CampaignChecks::blockingProblems($campaign)) {
            throw ValidationException::withMessages(['campaign' => $problems[0]]);
        }

        $campaign->update(['status' => EmailCampaign::STATUS_SCHEDULED, 'scheduled_at' => $at, 'last_error' => null]);

        return $campaign->fresh();
    }

    public function unschedule(EmailCampaign $campaign): EmailCampaign
    {
        if ($campaign->status !== EmailCampaign::STATUS_SCHEDULED) {
            throw ValidationException::withMessages(['campaign' => 'This campaign isn\'t scheduled.']);
        }

        $campaign->update(['status' => EmailCampaign::STATUS_DRAFT, 'scheduled_at' => null]);

        return $campaign->fresh();
    }

    /**
     * Called every minute by the scheduler. A campaign that can no longer be sent (for
     * example its list was deleted) goes back to draft with the reason shown.
     *
     * @return array{started: int, failed: int}
     */
    public function sendDueScheduled(): array
    {
        $result = ['started' => 0, 'failed' => 0];

        EmailCampaign::where('status', EmailCampaign::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->each(function (EmailCampaign $campaign) use (&$result): void {
                try {
                    $this->start($campaign);
                    $result['started']++;
                } catch (ValidationException $exception) {
                    $campaign->update([
                        'status' => EmailCampaign::STATUS_DRAFT,
                        'last_error' => 'The scheduled send on '.$campaign->scheduled_at?->timezone('Europe/London')->format('j M Y, H:i').' didn\'t go out: '.collect($exception->errors())->flatten()->first(),
                        'scheduled_at' => null,
                    ]);
                    $result['failed']++;
                }
            });

        return $result;
    }

    /**
     * Sends up to the per-minute limit. Returns true if people are still waiting.
     */
    public function sendBatch(EmailCampaign $campaign): bool
    {
        if ($campaign->status !== EmailCampaign::STATUS_SENDING) {
            return false;
        }

        $ids = $campaign->recipients()
            ->where('status', EmailCampaignRecipient::STATUS_PENDING)
            ->orderBy('id')
            ->limit((int) config('email-marketing.rate_per_minute'))
            ->pluck('id');

        foreach ($ids as $id) {
            $this->sendOne($campaign, $id);
        }

        $remaining = $this->refreshCounts($campaign);
        if ($remaining === 0) {
            $campaign->update(['status' => EmailCampaign::STATUS_SENT, 'sent_at' => now()]);
        }

        return $remaining > 0;
    }

    private function sendOne(EmailCampaign $campaign, int $recipientId): void
    {
        // Claim the row first so two workers can never email the same person.
        $claimed = EmailCampaignRecipient::whereKey($recipientId)
            ->where('status', EmailCampaignRecipient::STATUS_PENDING)
            ->update(['status' => EmailCampaignRecipient::STATUS_SENDING, 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
        if ($claimed === 0) {
            return;
        }

        $recipient = EmailCampaignRecipient::with('subscriber')->findOrFail($recipientId);

        if ($this->suppressions->isSuppressed($recipient->email) || $recipient->subscriber?->status !== EmailSubscriber::STATUS_SUBSCRIBED) {
            $recipient->update(['status' => EmailCampaignRecipient::STATUS_SKIPPED, 'error' => 'Unsubscribed, bounced or removed before sending']);

            return;
        }

        $token = $recipient->issueToken();
        $unsubscribeUrl = route('email-marketing.unsubscribe', ['token' => $token]);

        $result = $this->mailer->send($campaign, $recipient->email, [
            'first_name' => $recipient->first_name,
            'last_name' => $recipient->last_name,
            'email' => $recipient->email,
            'unsubscribe_url' => $unsubscribeUrl,
            'view_in_browser_url' => route('email-marketing.view', ['token' => $token]),
        ], 'campaign', [
            // RFC 8058 one-click unsubscribe, as Gmail and Yahoo require for bulk senders.
            'List-Unsubscribe' => '<'.$unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ], recipientId: $recipient->id);

        $recipient->update($result['ok']
            ? ['status' => EmailCampaignRecipient::STATUS_SENT, 'sent_at' => now(), 'provider_message_id' => $result['message_id'], 'error' => null]
            : ['status' => EmailCampaignRecipient::STATUS_FAILED, 'error' => $result['error']]);

        // The provider refused the address outright: treat it as a hard bounce.
        if (!$result['ok'] && preg_match('/\b(550|551|553|5\.1\.1|5\.1\.10)\b/', (string) $result['error'])) {
            $this->suppressions->suppress($recipient->email, EmailSubscriber::STATUS_BOUNCED, 'campaign:'.$campaign->id, mb_substr((string) $result['error'], 0, 500));
        }
    }

    public function unsubscribe(EmailCampaignRecipient $recipient): void
    {
        $this->suppressions->suppress($recipient->email, EmailSubscriber::STATUS_UNSUBSCRIBED, 'campaign:'.$recipient->email_campaign_id, 'Unsubscribed using the link in the email');

        if ($recipient->unsubscribed_at === null) {
            $recipient->update(['unsubscribed_at' => now()]);
        }

        $campaign = $recipient->campaign;
        $campaign->update(['unsubscribed_count' => $campaign->recipients()->whereNotNull('unsubscribed_at')->count()]);
    }

    public function retryFailed(EmailCampaign $campaign): int
    {
        $count = $campaign->recipients()->where('status', EmailCampaignRecipient::STATUS_FAILED)
            ->update(['status' => EmailCampaignRecipient::STATUS_PENDING, 'error' => null]);

        if ($count > 0) {
            $campaign->update(['status' => EmailCampaign::STATUS_SENDING]);
            SendEmailCampaignBatch::dispatch($campaign->id);
        }

        return $count;
    }

    /**
     * Picks up campaigns whose queue job died part-way. People claimed but never confirmed
     * are marked failed rather than resent, so nobody gets the email twice.
     */
    public function resumeStalled(): int
    {
        $resumed = 0;
        EmailCampaign::where('status', EmailCampaign::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes(5))
            ->each(function (EmailCampaign $campaign) use (&$resumed): void {
                $campaign->recipients()
                    ->where('status', EmailCampaignRecipient::STATUS_SENDING)
                    ->where('updated_at', '<', now()->subMinutes(15))
                    ->update(['status' => EmailCampaignRecipient::STATUS_FAILED, 'error' => 'Sending was interrupted, so delivery could not be confirmed. Use Retry failed to send again.']);

                $campaign->touch();
                SendEmailCampaignBatch::dispatch($campaign->id);
                $resumed++;
            });

        return $resumed;
    }

    /**
     * @return int People still waiting to be sent.
     */
    public function refreshCounts(EmailCampaign $campaign): int
    {
        $counts = $campaign->recipients()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        $campaign->update([
            'sent_count' => (int) ($counts[EmailCampaignRecipient::STATUS_SENT] ?? 0),
            'failed_count' => (int) ($counts[EmailCampaignRecipient::STATUS_FAILED] ?? 0),
            'unsubscribed_count' => $campaign->recipients()->whereNotNull('unsubscribed_at')->count(),
        ]);
        $campaign->touch();

        return (int) ($counts[EmailCampaignRecipient::STATUS_PENDING] ?? 0) + (int) ($counts[EmailCampaignRecipient::STATUS_SENDING] ?? 0);
    }
}
