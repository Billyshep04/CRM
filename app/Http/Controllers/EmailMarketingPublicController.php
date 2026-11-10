<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaignRecipient;
use App\Services\EmailMarketing\CampaignMailer;
use App\Services\EmailMarketing\CampaignSender;
use App\Services\EmailMarketing\MergeTags;
use App\Services\EmailMarketing\SuppressionService;
use App\Services\OrganisationSettings;
use Illuminate\Http\Request;

/**
 * Public pages linked from campaign emails. No login: the per-recipient token is the key.
 */
class EmailMarketingPublicController extends Controller
{
    public function showUnsubscribe(string $token, SuppressionService $suppressions, OrganisationSettings $settings)
    {
        if ($token === 'test') {
            return $this->page($settings, 'test', ['message' => 'This link came from a test email, so nothing has changed. In the real campaign it unsubscribes the person it was sent to.']);
        }

        $recipient = EmailCampaignRecipient::findByToken($token);
        if (!$recipient) {
            return $this->page($settings, 'invalid', [], 404);
        }

        return $this->page($settings, $suppressions->isSuppressed($recipient->email) ? 'done' : 'confirm', [
            'email' => $recipient->email,
            'token' => $token,
        ]);
    }

    /**
     * Handles both the button on the page and RFC 8058 one-click requests from mail apps.
     */
    public function unsubscribe(Request $request, string $token, CampaignSender $sender, OrganisationSettings $settings)
    {
        $recipient = $token === 'test' ? null : EmailCampaignRecipient::findByToken($token);
        if (!$recipient) {
            return $token === 'test'
                ? $this->page($settings, 'test', ['message' => 'This link came from a test email, so nothing has changed.'])
                : $this->page($settings, 'invalid', [], 404);
        }

        $sender->unsubscribe($recipient);

        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('Unsubscribed', 200)->header('Content-Type', 'text/plain');
        }

        return $this->page($settings, 'done', ['email' => $recipient->email]);
    }

    public function view(string $token, OrganisationSettings $settings)
    {
        if ($token === 'test') {
            return $this->page($settings, 'test', ['message' => 'This "view online" link came from a test email. In the real campaign it opens that person\'s copy of the email.']);
        }

        $recipient = EmailCampaignRecipient::findByToken($token);
        if (!$recipient || !$recipient->campaign) {
            return $this->page($settings, 'invalid', [], 404);
        }

        $campaign = $recipient->campaign;
        $html = MergeTags::render(CampaignMailer::withPreviewText((string) $campaign->html_body, $campaign->preview_text), [
            'first_name' => $recipient->first_name,
            'last_name' => $recipient->last_name,
            'email' => $recipient->email,
            'unsubscribe_url' => route('email-marketing.unsubscribe', ['token' => $token]),
            'view_in_browser_url' => route('email-marketing.view', ['token' => $token]),
        ]);

        // The email is served on the CRM's domain, so it runs sandboxed with no scripts:
        // it can never read the CRM's login session even if the HTML contained script.
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "sandbox allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation; script-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'",
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private function page(OrganisationSettings $settings, string $state, array $data = [], int $status = 200)
    {
        $organisation = $settings->all();

        return response()->view('email-marketing.public', [
            'state' => $state,
            'companyName' => trim((string) ($organisation['company_name'] ?? '')) ?: config('app.name'),
            'colour' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($organisation['primary_colour'] ?? '')) ? $organisation['primary_colour'] : '#35b8ef',
            ...$data,
        ], $status)->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
