<?php

namespace App\Services\EmailMarketing;

use App\Models\EmailCampaign;

/**
 * What must be true before a campaign can go to a list.
 */
class CampaignChecks
{
    /**
     * @return list<string> Problems that block sending. Empty means ready.
     */
    public static function blockingProblems(EmailCampaign $campaign, bool $forTest = false): array
    {
        $problems = [];
        $html = (string) $campaign->html_body;

        if (trim((string) $campaign->subject) === '') {
            $problems[] = 'Add a subject line.';
        }
        if (trim((string) $campaign->from_email) === '' || trim((string) $campaign->from_name) === '') {
            $problems[] = 'Add a from name and from email.';
        } elseif (($domainProblem = self::fromDomainProblem((string) $campaign->from_email)) !== null) {
            $problems[] = $domainProblem;
        }
        if (trim($html) === '') {
            $problems[] = 'Add the email HTML.';
        } elseif (!$forTest && !MergeTags::contains($html, 'unsubscribe_url')) {
            $problems[] = 'The email must include an unsubscribe link. Add {{unsubscribe_url}} to the HTML, for example <a href="{{unsubscribe_url}}">Unsubscribe</a>.';
        }
        if ($unknown = MergeTags::unknown($html)) {
            $problems[] = 'Unknown merge tags: '.implode(', ', array_map(fn ($tag) => '{{'.$tag.'}}', $unknown)).'. Supported tags are '.implode(', ', array_map(fn ($tag) => '{{'.$tag.'}}', MergeTags::SUPPORTED)).'.';
        }
        if (!$forTest && $campaign->lists()->count() === 0) {
            $problems[] = 'Choose at least one list to send to.';
        }

        return $problems;
    }

    public static function fromDomainProblem(string $fromEmail): ?string
    {
        $allowed = config('email-marketing.allowed_from_domains', []);
        $domain = strtolower((string) substr(strrchr($fromEmail, '@') ?: '', 1));

        if ($allowed === []) {
            return 'No sending domain is configured. Set MARKETING_ALLOWED_FROM_DOMAINS in the .env file.';
        }

        return in_array($domain, $allowed, true)
            ? null
            : 'The from email must be on '.implode(' or ', $allowed).', the domain set up for sending.';
    }
}
