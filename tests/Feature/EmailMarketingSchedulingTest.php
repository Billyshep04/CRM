<?php

namespace Tests\Feature;

use App\Models\EmailCampaign;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailMarketingSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mail.mailers.marketing' => ['transport' => 'array'],
            'email-marketing.allowed_from_domains' => ['web-stamp.co.uk'],
        ]);
        $this->actingAsRole('admin');
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(9, 0));
    }

    public function test_campaign_can_be_scheduled_and_sends_when_due(): void
    {
        $campaign = $this->campaign();

        $this->postJson("/api/email-campaigns/{$campaign->id}/schedule", ['scheduled_at' => '2026-11-14T09:00:00+00:00', 'confirmed' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');
        $this->assertSame('2026-11-14 09:00:00', $campaign->fresh()->scheduled_at->toDateTimeString());

        // Nothing happens before the time.
        $this->artisan('email-campaigns:send-scheduled')->assertSuccessful();
        $this->assertSame('scheduled', $campaign->fresh()->status);
        $this->assertCount(0, $this->sent());

        // People who join the list before the send are included: recipients are worked out at send time.
        $this->subscribe('late@example.com', $campaign->lists()->first());

        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(9, 0, 30));
        $this->artisan('email-campaigns:send-scheduled')->assertSuccessful();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->recipients_count);
        $this->assertCount(2, $this->sent());
    }

    public function test_scheduling_is_validated_up_front(): void
    {
        $campaign = $this->campaign();

        $this->postJson("/api/email-campaigns/{$campaign->id}/schedule", ['scheduled_at' => '2026-11-10T08:00:00+00:00', 'confirmed' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $this->postJson("/api/email-campaigns/{$campaign->id}/schedule", ['scheduled_at' => '2028-01-01T08:00:00+00:00', 'confirmed' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $this->postJson("/api/email-campaigns/{$campaign->id}/schedule", ['scheduled_at' => '2026-11-14T09:00:00+00:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmed');

        $campaign->update(['html_body' => '<p>No unsubscribe link</p>']);
        $this->postJson("/api/email-campaigns/{$campaign->id}/schedule", ['scheduled_at' => '2026-11-14T09:00:00+00:00', 'confirmed' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.campaign.0', fn ($message) => str_contains($message, '{{unsubscribe_url}}'));
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_scheduled_campaign_can_be_edited_or_cancelled(): void
    {
        $campaign = $this->campaign();
        $this->postJson("/api/email-campaigns/{$campaign->id}/schedule", ['scheduled_at' => '2026-11-14T09:00:00+00:00', 'confirmed' => true])->assertOk();

        $this->putJson("/api/email-campaigns/{$campaign->id}", ['name' => 'Renamed', 'subject' => 'New subject', 'from_name' => 'WebStamp', 'from_email' => 'hello@web-stamp.co.uk', 'html_body' => $campaign->html_body, 'list_ids' => $campaign->lists()->pluck('email_lists.id')->all()])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');

        $this->postJson("/api/email-campaigns/{$campaign->id}/unschedule")->assertOk()->assertJsonPath('data.status', 'draft');
        $this->assertNull($campaign->fresh()->scheduled_at);
        $this->postJson("/api/email-campaigns/{$campaign->id}/unschedule")->assertUnprocessable();

        $this->travelTo(now()->setDate(2026, 11, 15));
        $this->artisan('email-campaigns:send-scheduled');
        $this->assertCount(0, $this->sent());
    }

    public function test_scheduled_send_that_can_no_longer_go_out_returns_to_draft_with_the_reason(): void
    {
        $campaign = $this->campaign();
        $this->postJson("/api/email-campaigns/{$campaign->id}/schedule", ['scheduled_at' => '2026-11-14T09:00:00+00:00', 'confirmed' => true])->assertOk();

        // The list is deleted after scheduling.
        $campaign->lists()->first()->delete();

        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(9, 1));
        $this->artisan('email-campaigns:send-scheduled')->assertSuccessful();

        $campaign->refresh();
        $this->assertSame('draft', $campaign->status);
        $this->assertNull($campaign->scheduled_at);
        $this->assertStringContainsString('14 Nov 2026, 09:00', $campaign->last_error);
        $this->assertStringContainsString('Choose at least one list', $campaign->last_error);
        $this->assertCount(0, $this->sent());

        // Sending successfully later clears the message.
        $campaign->lists()->attach(EmailList::create(['name' => 'New list'])->id);
        $this->subscribe('sam@example.com', $campaign->lists()->first());
        $this->postJson("/api/email-campaigns/{$campaign->id}/send", ['confirmed' => true])->assertOk();
        $this->assertNull($campaign->fresh()->last_error);
    }

    private function campaign(): EmailCampaign
    {
        $list = EmailList::create(['name' => 'Newsletter']);
        $this->subscribe('sam@example.com', $list);

        $campaign = EmailCampaign::create([
            'name' => 'November',
            'subject' => 'Hello',
            'from_name' => 'WebStamp',
            'from_email' => 'hello@web-stamp.co.uk',
            'html_body' => '<body><p>Hi {{first_name|there}}</p><a href="{{unsubscribe_url}}">Unsubscribe</a></body>',
            'status' => 'draft',
        ]);
        $campaign->lists()->attach($list->id);

        return $campaign;
    }

    private function subscribe(string $email, EmailList $list): void
    {
        EmailSubscriber::firstOrCreate(['email' => $email], ['consent_source' => 'manual'])->lists()->syncWithoutDetaching([$list->id => ['added_via' => 'manual']]);
    }

    private function sent()
    {
        return Mail::mailer('marketing')->getSymfonyTransport()->messages();
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
