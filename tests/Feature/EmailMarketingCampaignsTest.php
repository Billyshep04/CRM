<?php

namespace Tests\Feature;

use App\Models\EmailCampaign;
use App\Models\EmailList;
use App\Models\EmailSendAttempt;
use App\Models\EmailSubscriber;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailMarketing\CampaignChecks;
use App\Services\EmailMarketing\HtmlToText;
use App\Services\EmailMarketing\MergeTags;
use App\Services\EmailMarketing\SuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailMarketingCampaignsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mail.mailers.marketing' => ['transport' => 'array'],
            'email-marketing.allowed_from_domains' => ['web-stamp.co.uk'],
        ]);
    }

    public function test_merge_tags_are_replaced_with_fallbacks_and_escaping(): void
    {
        $values = ['first_name' => 'Sam <b>', 'last_name' => '', 'email' => 'sam@example.com', 'unsubscribe_url' => 'https://crm.test/u/abc?x=1&y=2'];

        $this->assertSame('Hi Sam &lt;b&gt;,', MergeTags::render('Hi {{first_name}},', $values));
        $this->assertSame('Hi there,', MergeTags::render('Hi {{ last_name | there }},', $values));
        $this->assertSame('Hi ,', MergeTags::render('Hi {{last_name}},', $values));
        $this->assertSame('<a href="https://crm.test/u/abc?x=1&amp;y=2">', MergeTags::render('<a href="{{unsubscribe_url}}">', $values));
        $this->assertSame('https://crm.test/u/abc?x=1&y=2', MergeTags::render('{{unsubscribe_url}}', $values, false));
        $this->assertSame('{{company}} and {{ first_name', MergeTags::render('{{company}} and {{ first_name', $values));
        $this->assertSame('SAM@EXAMPLE.COM', strtoupper(MergeTags::render('{{EMAIL}}', $values)));
        $this->assertSame(['company'], MergeTags::unknown('{{first_name}} {{company}} {{company|x}}'));
    }

    public function test_test_send_delivers_the_html_exactly_as_written_with_a_plain_text_part(): void
    {
        $admin = $this->actingAsRole('admin');
        $html = $this->template();
        $campaign = $this->campaign(['html_body' => $html]);

        $this->postJson("/api/email-campaigns/{$campaign->id}/test", ['emails' => 'billy@web-stamp.co.uk'])
            ->assertOk()
            ->assertJsonPath('results.0.ok', true);

        $messages = Mail::mailer('marketing')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        /** @var Email $email */
        $email = $messages->first()->getOriginalMessage();

        $expected = str_replace(
            ['{{view_in_browser_url}}', '{{unsubscribe_url}}'],
            [url('/email/view/test'), url('/email/unsubscribe/test')],
            $html,
        );
        $this->assertSame($expected, $email->getHtmlBody(), 'The HTML must be sent byte-for-byte, apart from merge tags.');
        $this->assertStringContainsString('<!--[if mso]><v:roundrect', $email->getHtmlBody());
        $this->assertNotEmpty($email->getTextBody());
        $this->assertSame('multipart', $email->getBody()->getMediaType());
        $this->assertSame('alternative', $email->getBody()->getMediaSubtype());

        $this->assertSame('[Test] Can customers find you on Google?', $email->getSubject());
        $this->assertCount(1, $email->getTo());
        $this->assertSame('billy@web-stamp.co.uk', $email->getTo()[0]->getAddress());
        $this->assertSame([], $email->getCc());
        $this->assertSame('hello@web-stamp.co.uk', $email->getFrom()[0]->getAddress());

        $this->assertDatabaseHas('email_send_attempts', ['email_campaign_id' => $campaign->id, 'type' => 'test', 'email' => 'billy@web-stamp.co.uk', 'status' => 'sent', 'created_by_user_id' => $admin->id]);
    }

    public function test_plain_text_version_skips_outlook_markup_styles_and_preheader(): void
    {
        $text = HtmlToText::convert($this->template());

        $this->assertStringContainsString('Get a free SEO audit (https://web-stamp.co.uk/contact/?utm_source=email&utm_medium=newsletter&utm_campaign=seo-audit)', $text);
        $this->assertStringContainsString('Unsubscribe ({{unsubscribe_url}})', $text);
        $this->assertStringContainsString('Page two is invisible', $text);
        $this->assertStringContainsString('WebStamp (https://web-stamp.co.uk/?utm_source=email&utm_medium=newsletter&utm_campaign=seo-audit)', $text);
        $this->assertStringContainsString('Local SEO & Maps', $text);
        $this->assertStringContainsString('or call 07979 971328', $text);
        $this->assertStringNotContainsString('GET A FREE SEO AUDIT', $text, 'Outlook-only VML must not appear');
        $this->assertStringNotContainsString('keeping you off page one', $text, 'Hidden preheader must not appear');
        $this->assertStringNotContainsString('-webkit-text-size-adjust', $text);
        $this->assertStringNotContainsString('<', $text);
        $this->assertDoesNotMatchRegularExpression("/\n{3,}/", $text);
    }

    public function test_sending_is_blocked_without_an_unsubscribe_link(): void
    {
        $list = EmailList::create(['name' => 'Newsletter']);
        $campaign = $this->campaign(['html_body' => '<p>Hello {{first_name|there}}</p>']);
        $campaign->lists()->attach($list->id);

        $problems = CampaignChecks::blockingProblems($campaign);
        $this->assertCount(1, $problems);
        $this->assertStringContainsString('{{unsubscribe_url}}', $problems[0]);

        $campaign->update(['html_body' => '<p>Hello</p><a href="{{ unsubscribe_url }}">Unsubscribe</a>']);
        $this->assertSame([], CampaignChecks::blockingProblems($campaign));

        $campaign->update(['html_body' => '<a href="{{unsubscribe_url}}">x</a> {{company}}']);
        $this->assertStringContainsString('{{company}}', CampaignChecks::blockingProblems($campaign)[0]);
    }

    public function test_admin_can_create_update_list_and_duplicate_campaigns(): void
    {
        $this->actingAsRole('admin');
        $list = EmailList::create(['name' => 'Newsletter']);

        $id = $this->postJson('/api/email-campaigns', ['name' => 'November SEO', 'list_ids' => [$list->id]])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.list_ids', [$list->id])
            ->json('data.id');

        $this->putJson("/api/email-campaigns/{$id}", [
            'name' => 'November SEO',
            'subject' => 'Can customers find you?',
            'from_name' => 'WebStamp',
            'from_email' => 'hello@web-stamp.co.uk',
            'html_body' => $this->template(),
            'list_ids' => [$list->id],
        ])->assertOk()->assertJsonPath('data.problems', []);

        $this->getJson('/api/email-campaigns')->assertOk()->assertJsonPath('data.0.name', 'November SEO')->assertJsonPath('data.0.lists.0.name', 'Newsletter');

        $copy = $this->postJson("/api/email-campaigns/{$id}/duplicate")->assertCreated()->json('data');
        $this->assertSame('Copy of November SEO', $copy['name']);
        $this->assertSame($this->template(), EmailCampaign::find($copy['id'])->html_body);
    }

    public function test_from_email_must_be_on_the_sending_domain(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/email-campaigns', ['name' => 'x', 'from_email' => 'me@gmail.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from_email');
    }

    public function test_sent_campaigns_are_locked(): void
    {
        $this->actingAsRole('admin');
        $campaign = $this->campaign(['status' => EmailCampaign::STATUS_SENT]);

        $this->putJson("/api/email-campaigns/{$campaign->id}", ['name' => 'Changed'])->assertUnprocessable();
        $this->deleteJson("/api/email-campaigns/{$campaign->id}")->assertUnprocessable();
        $this->postJson("/api/email-campaigns/{$campaign->id}/duplicate")->assertCreated();

        $draft = $this->campaign();
        $this->deleteJson("/api/email-campaigns/{$draft->id}")->assertOk();
    }

    public function test_test_send_validates_addresses_and_logs_failures(): void
    {
        $this->actingAsRole('admin');
        $campaign = $this->campaign();

        $this->postJson("/api/email-campaigns/{$campaign->id}/test", ['emails' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('emails');
        $this->postJson("/api/email-campaigns/{$campaign->id}/test", ['emails' => 'a@x.com,b@x.com,c@x.com,d@x.com,e@x.com,f@x.com'])->assertUnprocessable();

        config(['mail.mailers.marketing' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);
        app('mail.manager')->forgetMailers();
        $this->postJson("/api/email-campaigns/{$campaign->id}/test", ['emails' => 'a@x.com'])->assertUnprocessable()->assertJsonPath('results.0.ok', false);
        $this->assertSame('failed', EmailSendAttempt::latest('id')->first()->status);
        $this->assertNotEmpty(EmailSendAttempt::latest('id')->first()->error);
    }

    public function test_recipient_count_dedupes_across_lists_and_excludes_suppressed_people(): void
    {
        $admin = $this->actingAsRole('admin');
        $one = EmailList::create(['name' => 'One']);
        $two = EmailList::create(['name' => 'Two']);
        foreach (['a@x.com' => [$one, $two], 'b@x.com' => [$one], 'c@x.com' => [$two], 'd@x.com' => [$two]] as $email => $lists) {
            $subscriber = EmailSubscriber::create(['email' => $email, 'consent_source' => 'manual']);
            foreach ($lists as $list) {
                $subscriber->lists()->attach($list->id, ['added_via' => 'manual']);
            }
        }
        app(SuppressionService::class)->suppress('c@x.com', 'unsubscribed', 'test', null, $admin);

        $this->getJson("/api/email-campaigns/recipient-count?list_ids[]={$one->id}&list_ids[]={$two->id}")->assertJsonPath('count', 3);
        $this->getJson("/api/email-campaigns/recipient-count?list_ids[]={$two->id}")->assertJsonPath('count', 2);
    }

    public function test_preview_text_is_only_added_when_filled_in(): void
    {
        $this->actingAsRole('admin');
        $campaign = $this->campaign(['preview_text' => 'Free audit this month']);

        $this->postJson("/api/email-campaigns/{$campaign->id}/test", ['emails' => 'billy@web-stamp.co.uk'])->assertOk();
        $html = Mail::mailer('marketing')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getHtmlBody();

        $this->assertMatchesRegularExpression('/^<body><div style="display:none;[^"]*">Free audit this month/', $html);
    }

    public function test_campaigns_are_admin_only(): void
    {
        $this->actingAsRole('staff');
        $this->getJson('/api/email-campaigns')->assertForbidden();
        $this->postJson('/api/email-campaigns/plain-text', ['html' => '<p>x</p>'])->assertForbidden();
    }

    private function campaign(array $attributes = []): EmailCampaign
    {
        return EmailCampaign::create($attributes + [
            'name' => 'November SEO',
            'subject' => 'Can customers find you on Google?',
            'from_name' => 'WebStamp',
            'from_email' => 'hello@web-stamp.co.uk',
            'html_body' => '<body><p>Hi {{first_name|there}}</p><a href="{{unsubscribe_url}}">Unsubscribe</a></body>',
            'status' => EmailCampaign::STATUS_DRAFT,
        ]);
    }

    private function template(): string
    {
        return file_get_contents(base_path('tests/Fixtures/email-marketing/seo-audit.html'));
    }

    private function actingAsRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => ucfirst($roleSlug)]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        Sanctum::actingAs($user);

        return $user;
    }
}
