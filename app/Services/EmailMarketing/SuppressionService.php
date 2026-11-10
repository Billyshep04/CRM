<?php

namespace App\Services\EmailMarketing;

use App\Models\EmailSubscriber;
use App\Models\EmailSuppression;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The global do-not-email list. Unsubscribes and bounces apply to every list,
 * and survive the address being removed, re-added or re-imported.
 */
class SuppressionService
{
    public function isSuppressed(string $email): bool
    {
        return EmailSuppression::active()->where('email', EmailSubscriber::normaliseEmail($email))->exists();
    }

    /**
     * @param  list<string>  $emails
     * @return array<string, true> Suppressed addresses (normalised) as keys.
     */
    public function suppressedAmong(array $emails): array
    {
        $normalised = array_values(array_unique(array_map(EmailSubscriber::normaliseEmail(...), $emails)));
        $found = [];

        foreach (array_chunk($normalised, 500) as $chunk) {
            EmailSuppression::active()->whereIn('email', $chunk)->pluck('email')
                ->each(function (string $email) use (&$found): void {
                    $found[$email] = true;
                });
        }

        return $found;
    }

    /**
     * @param  'unsubscribed'|'bounced'  $reason
     */
    public function suppress(string $email, string $reason, ?string $source = null, ?string $note = null, ?User $by = null): void
    {
        $email = EmailSubscriber::normaliseEmail($email);

        DB::transaction(function () use ($email, $reason, $source, $note, $by): void {
            if (!EmailSuppression::active()->where('email', $email)->exists()) {
                EmailSuppression::create([
                    'email' => $email,
                    'reason' => $reason,
                    'source' => $source,
                    'note' => $note,
                    'created_by_user_id' => $by?->id,
                ]);
            }

            $subscriber = EmailSubscriber::where('email', $email)->first();
            if ($subscriber) {
                $subscriber->update($reason === EmailSubscriber::STATUS_BOUNCED
                    ? ['status' => EmailSubscriber::STATUS_BOUNCED, 'bounced_at' => $subscriber->bounced_at ?? now()]
                    : ['status' => EmailSubscriber::STATUS_UNSUBSCRIBED, 'unsubscribed_at' => $subscriber->unsubscribed_at ?? now()]);
            }
        });
    }

    /**
     * Only an admin can lift a suppression, and must say why (e.g. "Asked to rejoin by email on 3 Nov").
     */
    public function resubscribe(EmailSubscriber $subscriber, string $note, User $by): void
    {
        DB::transaction(function () use ($subscriber, $note, $by): void {
            EmailSuppression::active()->where('email', $subscriber->email)->update([
                'lifted_at' => now(),
                'lifted_by_user_id' => $by->id,
                'lift_note' => $note,
            ]);

            $subscriber->update([
                'status' => EmailSubscriber::STATUS_SUBSCRIBED,
                'unsubscribed_at' => null,
                'bounced_at' => null,
            ]);
        });
    }
}
