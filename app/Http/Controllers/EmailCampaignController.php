<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaign;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Services\EmailMarketing\CampaignChecks;
use App\Models\EmailCampaignRecipient;
use App\Services\EmailMarketing\CampaignMailer;
use App\Services\EmailMarketing\CampaignSender;
use App\Services\EmailMarketing\HtmlToText;
use App\Services\EmailMarketing\MergeTags;
use App\Services\EmailMarketing\Recipients;
use App\Services\EmailMarketing\SubscriberImporter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailCampaignController extends Controller
{
    public function options()
    {
        return response()->json([
            'defaults' => [
                'from_name' => config('email-marketing.from_name'),
                'from_email' => config('email-marketing.from_address'),
                'reply_to' => config('email-marketing.reply_to'),
            ],
            'allowed_from_domains' => config('email-marketing.allowed_from_domains'),
            'merge_tags' => MergeTags::SUPPORTED,
            'max_test_recipients' => config('email-marketing.max_test_recipients'),
            'rate_per_minute' => (int) config('email-marketing.rate_per_minute'),
            'lists' => EmailList::query()
                ->withCount(['subscribers as subscribed_count' => fn ($q) => $q->where('status', EmailSubscriber::STATUS_SUBSCRIBED)])
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function index(Request $request)
    {
        $query = EmailCampaign::query()
            ->with('lists:id,name')
            ->select(['id', 'name', 'subject', 'status', 'scheduled_at', 'started_at', 'sent_at', 'recipients_count', 'sent_count', 'failed_count', 'unsubscribed_count', 'created_at', 'updated_at'])
            ->orderByRaw("CASE status WHEN 'sending' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%"));
        }
        if (in_array($status = $request->query('status'), [EmailCampaign::STATUS_DRAFT, EmailCampaign::STATUS_SCHEDULED, EmailCampaign::STATUS_SENDING, EmailCampaign::STATUS_SENT], true)) {
            $query->where('status', $status);
        }

        $page = $query->paginate($request->integer('per_page', 20));
        $page->getCollection()->each(function (EmailCampaign $campaign): void {
            // Drafts show who they'd reach today; sent campaigns show who they actually went to.
            $campaign->setAttribute('recipient_estimate', $campaign->isEditable() ? Recipients::count($campaign->lists->pluck('id')->all()) : null);
        });

        return response()->json($page);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $campaign = EmailCampaign::create([
            ...collect($data)->except('list_ids')->all(),
            'status' => EmailCampaign::STATUS_DRAFT,
            'created_by_user_id' => $request->user()?->id,
        ]);
        $campaign->lists()->sync($data['list_ids'] ?? []);

        return response()->json(['data' => $this->present($campaign)], 201);
    }

    public function show(EmailCampaign $emailCampaign)
    {
        return response()->json(['data' => $this->present($emailCampaign)]);
    }

    public function update(Request $request, EmailCampaign $emailCampaign)
    {
        $this->ensureEditable($emailCampaign);
        $data = $this->validated($request);
        $emailCampaign->update(collect($data)->except('list_ids')->all());
        $emailCampaign->lists()->sync($data['list_ids'] ?? []);

        return response()->json(['data' => $this->present($emailCampaign->fresh())]);
    }

    public function destroy(EmailCampaign $emailCampaign)
    {
        if (!$emailCampaign->isEditable()) {
            throw ValidationException::withMessages(['campaign' => 'Sent campaigns are kept as a record of what was sent.']);
        }

        $emailCampaign->delete();

        return response()->json(['message' => 'Campaign deleted.']);
    }

    public function duplicate(Request $request, EmailCampaign $emailCampaign)
    {
        $copy = EmailCampaign::create([
            ...$emailCampaign->only(['subject', 'preview_text', 'from_name', 'from_email', 'reply_to', 'html_body']),
            'name' => 'Copy of '.$emailCampaign->name,
            'status' => EmailCampaign::STATUS_DRAFT,
            'created_by_user_id' => $request->user()?->id,
        ]);
        $copy->lists()->sync($emailCampaign->lists()->pluck('email_lists.id'));

        return response()->json(['data' => $this->present($copy)], 201);
    }

    public function recipientCount(Request $request)
    {
        $listIds = array_map('intval', (array) $request->query('list_ids', []));

        return response()->json(['count' => Recipients::count($listIds)]);
    }

    public function plainText(Request $request)
    {
        $data = $request->validate(['html' => ['nullable', 'string', 'max:2000000']]);

        return response()->json(['text' => MergeTags::render(HtmlToText::convert((string) ($data['html'] ?? '')), $this->sampleValues(), false)]);
    }

    public function test(Request $request, EmailCampaign $emailCampaign, CampaignMailer $mailer)
    {
        $data = $request->validate(['emails' => ['required', 'string', 'max:2000']], ['emails.required' => 'Enter at least one email address.']);
        $max = (int) config('email-marketing.max_test_recipients');

        $emails = collect(preg_split('/[\s,;]+/', $data['emails']))
            ->map(fn ($email) => EmailSubscriber::normaliseEmail($email))
            ->filter()
            ->unique()
            ->values();

        if ($emails->count() > $max) {
            throw ValidationException::withMessages(['emails' => "Send a test to {$max} addresses at most."]);
        }
        if ($invalid = $emails->reject(fn ($email) => SubscriberImporter::isValidEmail($email))->first()) {
            throw ValidationException::withMessages(['emails' => "{$invalid} isn't a valid email address."]);
        }
        if ($problems = CampaignChecks::blockingProblems($emailCampaign, forTest: true)) {
            throw ValidationException::withMessages(['campaign' => $problems[0]]);
        }

        $results = $emails->map(function (string $email) use ($emailCampaign, $mailer, $request) {
            $subscriber = EmailSubscriber::where('email', $email)->first();
            $result = $mailer->send($emailCampaign, $email, [
                ...$this->sampleValues(),
                'first_name' => $subscriber?->first_name,
                'last_name' => $subscriber?->last_name,
                'email' => $email,
            ], 'test', [], '[Test]', $request->user());

            return ['email' => $email, 'ok' => $result['ok'], 'error' => $result['error']];
        })->all();

        $failed = collect($results)->where('ok', false);

        return response()->json([
            'message' => $failed->isEmpty()
                ? 'Test sent to '.$emails->implode(', ').'.'
                : 'Test failed for '.$failed->pluck('email')->implode(', ').': '.$failed->first()['error'],
            'results' => $results,
        ], $failed->isEmpty() ? 200 : 422);
    }

    /**
     * What the confirmation step shows before sending to the lists.
     */
    public function sendSummary(EmailCampaign $emailCampaign)
    {
        $listIds = $emailCampaign->lists()->pluck('email_lists.id')->all();

        return response()->json([
            'count' => Recipients::count($listIds),
            'lists' => $emailCampaign->lists()->pluck('name'),
            'problems' => CampaignChecks::blockingProblems($emailCampaign),
            'rate_per_minute' => (int) config('email-marketing.rate_per_minute'),
        ]);
    }

    public function send(Request $request, EmailCampaign $emailCampaign, CampaignSender $sender)
    {
        $request->validate(['confirmed' => ['accepted']], ['confirmed.accepted' => 'Confirm you want to send this campaign.']);

        $campaign = $sender->start($emailCampaign, $request->user());

        return response()->json(['data' => $this->present($campaign), 'message' => "Sending to {$campaign->recipients_count} people."]);
    }

    public function schedule(Request $request, EmailCampaign $emailCampaign, CampaignSender $sender)
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:'.now()->addMinutes(2)->toIso8601String(), 'before:'.now()->addYear()->toIso8601String()],
            'confirmed' => ['accepted'],
        ], [
            'scheduled_at.required' => 'Choose when to send it.',
            'scheduled_at.after' => 'Choose a time at least a few minutes from now.',
            'scheduled_at.before' => 'Choose a time within the next year.',
            'confirmed.accepted' => 'Confirm you want to schedule this campaign.',
        ]);

        $campaign = $sender->schedule($emailCampaign, \Illuminate\Support\Carbon::parse($data['scheduled_at'])->utc());

        return response()->json(['data' => $this->present($campaign), 'message' => 'Campaign scheduled.']);
    }

    public function unschedule(EmailCampaign $emailCampaign, CampaignSender $sender)
    {
        return response()->json(['data' => $this->present($sender->unschedule($emailCampaign)), 'message' => 'Schedule cancelled. The campaign is a draft again.']);
    }

    public function progress(EmailCampaign $emailCampaign)
    {
        $counts = $emailCampaign->recipients()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'status' => $emailCampaign->status,
            'recipients' => $emailCampaign->recipients_count,
            'sent' => (int) ($counts[EmailCampaignRecipient::STATUS_SENT] ?? 0),
            'failed' => (int) ($counts[EmailCampaignRecipient::STATUS_FAILED] ?? 0),
            'skipped' => (int) ($counts[EmailCampaignRecipient::STATUS_SKIPPED] ?? 0),
            'pending' => (int) ($counts[EmailCampaignRecipient::STATUS_PENDING] ?? 0) + (int) ($counts[EmailCampaignRecipient::STATUS_SENDING] ?? 0),
            'unsubscribed' => $emailCampaign->recipients()->whereNotNull('unsubscribed_at')->count(),
            'started_at' => $emailCampaign->started_at,
            'sent_at' => $emailCampaign->sent_at,
        ]);
    }

    /**
     * Per-recipient delivery log.
     */
    public function recipients(Request $request, EmailCampaign $emailCampaign)
    {
        $query = $emailCampaign->recipients()->orderBy('id');

        if (in_array($status = $request->query('status'), ['pending', 'sent', 'failed', 'skipped'], true)) {
            $query->where('status', $status);
        }
        if ($request->query('status') === 'unsubscribed') {
            $query->whereNotNull('unsubscribed_at');
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where('email', 'like', "%{$search}%");
        }

        return response()->json($query->paginate($request->integer('per_page', 25), ['id', 'email', 'first_name', 'last_name', 'status', 'error', 'attempts', 'sent_at', 'unsubscribed_at']));
    }

    public function retryFailed(EmailCampaign $emailCampaign, CampaignSender $sender)
    {
        if ($emailCampaign->isEditable()) {
            throw ValidationException::withMessages(['campaign' => 'This campaign hasn\'t been sent yet.']);
        }

        $count = $sender->retryFailed($emailCampaign);

        return response()->json(['message' => $count ? "Retrying {$count} failed ".($count === 1 ? 'email' : 'emails').'.' : 'Nothing to retry.', 'count' => $count]);
    }

    /**
     * Values used in previews and test sends. Real sends use each recipient's own links.
     *
     * @return array<string, ?string>
     */
    private function sampleValues(): array
    {
        return [
            'first_name' => null,
            'last_name' => null,
            'email' => 'name@example.com',
            'unsubscribe_url' => url('/email/unsubscribe/test'),
            'view_in_browser_url' => url('/email/view/test'),
        ];
    }

    private function ensureEditable(EmailCampaign $campaign): void
    {
        if (!$campaign->isEditable()) {
            throw ValidationException::withMessages(['campaign' => 'This campaign has already been sent, so it can\'t be changed. Duplicate it to make a new version.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EmailCampaign $campaign): array
    {
        $campaign->load('lists:id,name');
        $listIds = $campaign->lists->pluck('id')->all();

        return [
            ...$campaign->toArray(),
            'list_ids' => $listIds,
            'recipient_estimate' => $campaign->isEditable() ? Recipients::count($listIds) : null,
            'problems' => $campaign->isEditable() ? CampaignChecks::blockingProblems($campaign) : [],
            'html_size' => strlen((string) $campaign->html_body),
        ];
    }

    /**
     * Drafts can be saved incomplete; completeness is checked before sending.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'preview_text' => ['nullable', 'string', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_email' => ['nullable', 'email:rfc', 'max:255'],
            'reply_to' => ['nullable', 'email:rfc', 'max:255'],
            'html_body' => ['nullable', 'string', 'max:2000000'],
            'list_ids' => ['array'],
            'list_ids.*' => ['integer', 'exists:email_lists,id'],
        ], ['html_body.max' => 'The HTML is too large. Keep emails under 2 MB (ideally under 100 KB, or Gmail will cut them short).']);

        if (!empty($data['from_email']) && ($problem = CampaignChecks::fromDomainProblem($data['from_email'])) !== null) {
            throw ValidationException::withMessages(['from_email' => $problem]);
        }

        return $data;
    }
}
