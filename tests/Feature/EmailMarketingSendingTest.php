<?php

namespace Tests\Feature;

use App\Jobs\SendEmailCampaignBatch;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailMarketing\CampaignSender;
use App\Services\EmailMarketing\SuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailMarketingSendingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mail.mailers.marketing' => ['transport' => 'array'],
            'email-marketing.allowed_from_domains' => ['web-stamp.co.uk'],
            'email-marketing.rate_per_minute' => 60,
        ]);
        $this->admin = $this->actingAsRole('admin');
    }

    public function test_each_person_gets_their_own_personalised_email_with_one_click_unsubscribe_headers(): void
    {
        $campaign = $this->campaignFor(['sam@example.com' => 'Sam', 'alex@example.com' => null]);

        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])->assertOk();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->recipients_count);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertNotNull($campaign->sent_at);

        $emails = $this->sentEmails();
        $this->assertCount(2, $emails);
        foreach ($emails as $email) {
            $this->assertCount(1, $email->getTo());
            $this->assertSame([], $email->getCc());
            $this->assertSame([], $email->getBcc());
            $this->assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());
            $this->assertMatchesRegularExpression('#^<http://localhost/email/unsubscribe/[A-Za-z0-9]{48}>$#', $email->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
            $this->assertNotEmpty($email->getTextBody());
        }

        $byAddress = $emails->keyBy(fn (Email $email) => $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('Hi Sam,', $byAddress['sam@example.com']->getHtmlBody());
        $this->assertStringContainsString('Hi there,', $byAddress['alex@example.com']->getHtmlBody());
        $this->assertNotSame($this->unsubscribeUrl($byAddress['sam@example.com']), $this->unsubscribeUrl($byAddress['alex@example.com']));

        $this->assertDatabaseCount('email_send_attempts', 2);
        $this->assertSame(2, EmailCampaignRecipient::whereNotNull('sent_at')->count());
    }

    public function test_tokens_are_unguessable_stored_only_as_hashes_and_validated(): void
    {
        $campaign = $this->campaignFor(['sam@example.com' => 'Sam']);
        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])->assertOk();

        $token = basename($this->unsubscribeUrl($this->sentEmails()->first()));
        $recipient = EmailCampaignRecipient::first();

        $this->assertSame(48, strlen($token));
        $this->assertSame(hash('sha256', $token), $recipient->token_hash);
        $this->assertDatabaseMissing('email_campaign_recipients', ['token_hash' => $token]);
        $this->assertTrue($recipient->is(EmailCampaignRecipient::findByToken($token)));
        $this->assertNull(EmailCampaignRecipient::findByToken(substr($token, 0, -1).'x'));
        $this->assertNull(EmailCampaignRecipient::findByToken('short'));
        $this->assertArrayNotHasKey('token_hash', $recipient->toArray());

        $this->get("/email/unsubscribe/{$token}")->assertOk()->assertSee('sam@example.com')->assertSee('Unsubscribe from our emails?');
        $this->get('/email/unsubscribe/'.str_repeat('a', 48))->assertNotFound()->assertSee('We don\'t recognise that link', false);
    }

    public function test_unsubscribe_page_and_one_click_post_unsubscribe_globally(): void
    {
        $campaign = $this->campaignFor(['sam@example.com' => 'Sam', 'alex@example.com' => 'Alex']);
        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])->assertOk();
        $tokens = $this->sentEmails()->mapWithKeys(fn (Email $email) => [$email->getTo()[0]->getAddress() => basename($this->unsubscribeUrl($email))]);

        // Button on the page (no CSRF token, like a real browser visiting from an email).
        $this->post("/email/unsubscribe/{$tokens['sam@example.com']}")->assertOk()->assertSee('You\'re unsubscribed');
        // RFC 8058 one-click from a mail app.
        $this->call('POST', "/email/unsubscribe/{$tokens['alex@example.com']}", ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertSee('Unsubscribed');

        $suppressions = app(SuppressionService::class);
        $this->assertTrue($suppressions->isSuppressed('sam@example.com'));
        $this->assertTrue($suppressions->isSuppressed('alex@example.com'));
        $this->assertSame('unsubscribed', EmailSubscriber::firstWhere('email', 'sam@example.com')->status);
        $this->assertNotNull(EmailSubscriber::firstWhere('email', 'sam@example.com')->unsubscribed_at);
        $this->assertSame(2, $campaign->fresh()->unsubscribed_count);
        $this->getJson("/api/email-campaigns/{$campaign->id}/progress")->assertJsonPath('unsubscribed', 2)->assertJsonPath('sent', 2);

        // Visiting again shows they're already unsubscribed; a second campaign skips them.
        $this->get("/email/unsubscribe/{$tokens['sam@example.com']}")->assertSee('You\'re unsubscribed');
        $second = $this->campaignFor([], $campaign->lists()->first());
        $this->postJson("/api/email-campaigns/{$second->id}/send", ['confirmed' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.campaign.0', fn ($message) => str_contains($message, 'unsubscribed'));
    }

    public function test_people_suppressed_after_the_snapshot_are_skipped_at_send_time(): void
    {
        Queue::fake();
        $campaign = $this->campaignFor(['sam@example.com' => 'Sam', 'alex@example.com' => 'Alex', 'jo@example.com' => 'Jo']);
        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])->assertOk();
        $this->assertSame(3, $campaign->fresh()->recipients_count);
        Queue::assertPushed(SendEmailCampaignBatch::class);

        // Between pressing Send and the queue running, one person unsubscribes and one bounces.
        app(SuppressionService::class)->suppress('alex@example.com', 'unsubscribed');
        app(SuppressionService::class)->suppress('jo@example.com', 'bounced');

        (new SendEmailCampaignBatch($campaign->id))->handle(app(CampaignSender::class));

        $this->assertSame(['sam@example.com'], $this->sentEmails()->map(fn (Email $email) => $email->getTo()[0]->getAddress())->all());
        $this->assertSame('skipped', EmailCampaignRecipient::firstWhere('email', 'alex@example.com')->status);
        $this->assertSame('skipped', EmailCampaignRecipient::firstWhere('email', 'jo@example.com')->status);
        $this->assertSame('sent', $campaign->fresh()->status);
    }

    public function test_sending_happens_in_rate_limited_batches_on_the_queue(): void
    {
        Queue::fake();
        config(['email-marketing.rate_per_minute' => 2]);
        $campaign = $this->campaignFor(['a@x.com' => null, 'b@x.com' => null, 'c@x.com' => null, 'd@x.com' => null, 'e@x.com' => null]);

        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])->assertOk();
        $this->assertCount(0, $this->sentEmails(), 'Nothing is sent during the web request.');

        (new SendEmailCampaignBatch($campaign->id))->handle(app(CampaignSender::class));
        $this->assertCount(2, $this->sentEmails());
        $this->assertSame('sending', $campaign->fresh()->status);
        Queue::assertPushed(SendEmailCampaignBatch::class, fn ($job) => $job->campaignId === $campaign->id && $job->delay !== null);

        (new SendEmailCampaignBatch($campaign->id))->handle(app(CampaignSender::class));
        (new SendEmailCampaignBatch($campaign->id))->handle(app(CampaignSender::class));
        $this->assertCount(5, $this->sentEmails());
        $this->assertSame('sent', $campaign->fresh()->status);
        $this->getJson("/api/email-campaigns/{$campaign->id}/progress")->assertJsonPath('sent', 5)->assertJsonPath('pending', 0);
    }

    public function test_campaign_cannot_be_sent_without_unsubscribe_link_or_twice(): void
    {
        $campaign = $this->campaignFor(['sam@example.com' => 'Sam']);
        $campaign->update(['html_body' => '<p>No way out</p>']);

        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.campaign.0', fn ($message) => str_contains($message, '{{unsubscribe_url}}'));
        $this->assertSame('draft', $campaign->fresh()->status);

        $campaign->update(['html_body' => '<a href="{{unsubscribe_url}}">Unsubscribe</a>']);
        $this->postJson("/api/email-campaigns/{$campaign->id}/send", [])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])->assertOk();
        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])->assertUnprocessable();
        $this->assertCount(1, $this->sentEmails());
    }

    public function test_failures_are_logged_per_recipient_and_can_be_retried(): void
    {
        $campaign = $this->campaignFor(['sam@example.com' => 'Sam']);
        config(['mail.mailers.marketing' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);
        app('mail.manager')->forgetMailers();

        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])->assertOk();

        $recipient = EmailCampaignRecipient::first();
        $this->assertSame('failed', $recipient->status);
        $this->assertNotEmpty($recipient->error);
        $this->assertDatabaseHas('email_send_attempts', ['email_campaign_recipient_id' => $recipient->id, 'type' => 'campaign', 'status' => 'failed']);
        $this->getJson("/api/email-campaigns/{$campaign->id}/recipients?status=failed")->assertJsonPath('data.0.email', 'sam@example.com');

        config(['mail.mailers.marketing' => ['transport' => 'array']]);
        app('mail.manager')->forgetMailers();
        $this->postJson("/api/email-campaigns/{$campaign->id}/retry-failed")->assertOk()->assertJsonPath('count', 1);

        $this->assertSame('sent', $recipient->fresh()->status);
        $this->assertSame(2, $recipient->fresh()->attempts);
        $this->assertDatabaseCount('email_send_attempts', 2);
    }

    public function test_view_in_browser_shows_the_recipients_copy_sandboxed(): void
    {
        $campaign = $this->campaignFor(['sam@example.com' => 'Sam']);
        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])->assertOk();
        $token = basename($this->unsubscribeUrl($this->sentEmails()->first()));

        $response = $this->get("/email/view/{$token}")->assertOk()->assertSee('Hi Sam,', false);
        $this->assertStringContainsString("sandbox", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("script-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString(url("/email/unsubscribe/{$token}"), $response->getContent());

        $this->get('/email/view/test')->assertOk()->assertSee('This was a test email');
        $this->get('/email/unsubscribe/test')->assertOk()->assertSee('This was a test email');
    }

    public function test_interrupted_sends_are_resumed_without_resending(): void
    {
        Queue::fake();
        $campaign = $this->campaignFor(['sam@example.com' => 'Sam', 'alex@example.com' => 'Alex']);
        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])->assertOk();

        // A worker died after claiming Sam but before confirming the send.
        EmailCampaignRecipient::where('email', 'sam@example.com')->update(['status' => 'sending', 'updated_at' => now()->subMinutes(20)]);
        EmailCampaign::whereKey($campaign->id)->update(['updated_at' => now()->subMinutes(10)]);

        $this->assertSame(1, app(CampaignSender::class)->resumeStalled());
        $this->assertSame('failed', EmailCampaignRecipient::firstWhere('email', 'sam@example.com')->status);
        Queue::assertPushed(SendEmailCampaignBatch::class, 2);
    }

    /**
     * @param  array<string, ?string>  $people  email => first name
     */
    private function campaignFor(array $people, ?EmailList $list = null): EmailCampaign
    {
        $list ??= EmailList::create(['name' => 'Newsletter']);
        foreach ($people as $email => $firstName) {
            EmailSubscriber::create(['email' => $email, 'first_name' => $firstName, 'consent_source' => 'manual'])
                ->lists()->attach($list->id, ['added_via' => 'manual']);
        }

        $campaign = EmailCampaign::create([
            'name' => 'November',
            'subject' => 'Hello {{first_name|there}}',
            'from_name' => 'WebStamp',
            'from_email' => 'hello@web-stamp.co.uk',
            'html_body' => '<body><p>Hi {{first_name|there}},</p><a href="{{view_in_browser_url}}">View online</a> <a href="{{unsubscribe_url}}">Unsubscribe</a></body>',
            'status' => 'draft',
        ]);
        $campaign->lists()->attach($list->id);

        return $campaign;
    }

    /**
     * @return Collection<int, Email>
     */
    private function sentEmails(): Collection
    {
        return Mail::mailer('marketing')->getSymfonyTransport()->messages()->map(fn ($message) => $message->getOriginalMessage())->values();
    }

    private function unsubscribeUrl(Email $email): string
    {
        return trim($email->getHeaders()->get('List-Unsubscribe')->getBodyAsString(), '<>');
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
