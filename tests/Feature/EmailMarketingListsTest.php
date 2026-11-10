<?php

namespace Tests\Feature;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Models\EmailSuppression;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailMarketing\SuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailMarketingListsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_only_admins_can_use_email_marketing(): void
    {
        $this->actingAsRole('staff');
        $this->getJson('/api/email-lists')->assertForbidden();
        $this->postJson('/api/email-lists', ['name' => 'Newsletter'])->assertForbidden();

        $this->actingAsRole('customer');
        $this->getJson('/api/email-lists')->assertForbidden();
    }

    public function test_admin_can_create_edit_list_and_delete_lists(): void
    {
        $this->actingAsRole('admin');

        $id = $this->postJson('/api/email-lists', ['name' => 'Newsletter', 'description' => 'Monthly news'])
            ->assertCreated()
            ->assertJsonPath('data.subscribers_count', 0)
            ->json('data.id');

        $this->putJson("/api/email-lists/{$id}", ['name' => 'Monthly newsletter'])->assertOk()->assertJsonPath('data.name', 'Monthly newsletter');
        $this->getJson('/api/email-lists')->assertOk()->assertJsonPath('data.0.name', 'Monthly newsletter');

        $this->deleteJson("/api/email-lists/{$id}")->assertOk();
        $this->assertDatabaseCount('email_lists', 0);
    }

    public function test_manual_add_normalises_email_and_refuses_duplicates_and_invalid_addresses(): void
    {
        $this->actingAsRole('admin');
        $list = EmailList::create(['name' => 'Newsletter']);

        $this->postJson("/api/email-lists/{$list->id}/subscribers", ['email' => '  Sam@Example.CO.UK ', 'first_name' => 'Sam', 'consent_source' => 'website_form'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'sam@example.co.uk')
            ->assertJsonPath('data.status', 'subscribed')
            ->assertJsonPath('data.consent_source_label', 'Website form');

        $this->postJson("/api/email-lists/{$list->id}/subscribers", ['email' => 'SAM@example.co.uk', 'consent_source' => 'manual'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', 'That person is already on this list.');
        $this->postJson("/api/email-lists/{$list->id}/subscribers", ['email' => 'not-an-email', 'consent_source' => 'manual'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Enter a valid email address.');
        $this->postJson("/api/email-lists/{$list->id}/subscribers", ['email' => 'a@b.co', 'consent_source' => 'other'])
            ->assertUnprocessable()->assertJsonValidationErrors('consent_note');

        $this->assertSame(1, EmailSubscriber::count());
    }

    public function test_same_person_on_two_lists_is_one_subscriber(): void
    {
        $this->actingAsRole('admin');
        $first = EmailList::create(['name' => 'Newsletter']);
        $second = EmailList::create(['name' => 'Offers']);

        $this->postJson("/api/email-lists/{$first->id}/subscribers", ['email' => 'sam@example.com', 'consent_source' => 'manual'])->assertCreated();
        $this->postJson("/api/email-lists/{$second->id}/subscribers", ['email' => 'Sam@Example.com', 'consent_source' => 'manual'])->assertCreated();

        $this->assertSame(1, EmailSubscriber::count());
        $this->assertSame(2, EmailSubscriber::first()->lists()->count());
    }

    public function test_csv_import_maps_columns_and_reports_added_duplicate_skipped_and_invalid_rows(): void
    {
        $admin = $this->actingAsRole('admin');
        $list = EmailList::create(['name' => 'Newsletter']);
        app(SuppressionService::class)->suppress('gone@example.com', 'unsubscribed', 'test', null, $admin);
        EmailSubscriber::create(['email' => 'already@example.com', 'consent_source' => 'manual'])->lists()->attach($list->id, ['added_via' => 'manual']);

        $csv = "\xEF\xBB\xBFFirst Name;Surname;E-mail\n"
            ."Sam;Smith;sam@example.com\n"
            .";;\n"
            ."Sam;Again;SAM@EXAMPLE.COM\n"
            ."Gone;Person;Gone@Example.com\n"
            ."Already;Here;already@example.com\n"
            ."Bad;Row;not-an-email\n"
            ."No;Email;\n"
            ."Alex;Jones;alex@example.org\n";

        $preview = $this->post("/api/email-lists/{$list->id}/import/preview", ['file' => UploadedFile::fake()->createWithContent('people.csv', $csv)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.columns', ['First Name', 'Surname', 'E-mail'])
            ->assertJsonPath('data.mapping', ['email' => 2, 'first_name' => 0, 'last_name' => 1])
            ->json('data');

        $summary = $this->postJson("/api/email-lists/{$list->id}/import", [
            'token' => $preview['token'],
            'has_header' => true,
            'mapping' => $preview['mapping'],
            'consent_source' => 'website_form',
            'consent_confirmed' => true,
        ])->assertOk()->json('data');

        $this->assertSame(2, $summary['added']);
        $this->assertSame(2, $summary['duplicate']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(2, $summary['invalid']);
        $this->assertEqualsCanonicalizing(
            ['already@example.com', 'sam@example.com', 'alex@example.org'],
            $list->subscribers()->pluck('email')->all(),
        );
        $this->assertSame('Smith', EmailSubscriber::firstWhere('email', 'sam@example.com')->last_name);
        $this->assertSame('website_form', EmailSubscriber::firstWhere('email', 'alex@example.org')->consent_source);
        $this->assertDatabaseMissing('email_subscribers', ['email' => 'gone@example.com']);
        Storage::disk('local')->assertMissing("email-imports/{$preview['token']}.csv");
    }

    public function test_unsubscribed_person_already_on_the_list_is_reported_as_skipped(): void
    {
        $admin = $this->actingAsRole('admin');
        $list = EmailList::create(['name' => 'Newsletter']);
        EmailSubscriber::create(['email' => 'tom@example.net', 'consent_source' => 'manual'])->lists()->attach($list->id, ['added_via' => 'manual']);
        app(SuppressionService::class)->suppress('tom@example.net', 'unsubscribed', 'test', null, $admin);

        $token = $this->post("/api/email-lists/{$list->id}/import/preview", ['file' => UploadedFile::fake()->createWithContent('p.csv', "email\nTom@Example.net\n")], ['Accept' => 'application/json'])->json('data.token');
        $summary = $this->postJson("/api/email-lists/{$list->id}/import", ['token' => $token, 'mapping' => ['email' => 0], 'consent_source' => 'manual', 'consent_confirmed' => true])->json('data');

        $this->assertSame(['added' => 0, 'duplicate' => 0, 'skipped' => 1, 'invalid' => 0], array_diff_key($summary, ['details' => true]));
        $this->assertSame('Previously unsubscribed', $summary['details'][0]['reason']);
    }

    public function test_csv_import_requires_consent_confirmation(): void
    {
        $this->actingAsRole('admin');
        $list = EmailList::create(['name' => 'Newsletter']);
        $token = $this->post("/api/email-lists/{$list->id}/import/preview", ['file' => UploadedFile::fake()->createWithContent('p.csv', "email\na@example.com\n")], ['Accept' => 'application/json'])->json('data.token');

        $this->postJson("/api/email-lists/{$list->id}/import", ['token' => $token, 'mapping' => ['email' => 0], 'consent_source' => 'manual'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('consent_confirmed');
    }

    public function test_unsubscribe_is_global_and_survives_removal_and_reimport_until_resubscribed_with_a_note(): void
    {
        $this->actingAsRole('admin');
        $newsletter = EmailList::create(['name' => 'Newsletter']);
        $offers = EmailList::create(['name' => 'Offers']);
        $id = $this->postJson("/api/email-lists/{$newsletter->id}/subscribers", ['email' => 'sam@example.com', 'consent_source' => 'manual'])->json('data.id');

        $this->postJson("/api/email-subscribers/{$id}/unsubscribe", ['reason' => 'unsubscribed', 'note' => 'Asked by phone'])
            ->assertOk()->assertJsonPath('data.status', 'unsubscribed');
        $this->assertNotNull(EmailSubscriber::find($id)->unsubscribed_at);

        // Removing them and adding them back, to any list, is refused.
        $this->deleteJson("/api/email-lists/{$newsletter->id}/subscribers/{$id}")->assertOk();
        EmailSubscriber::whereKey($id)->delete();
        $this->postJson("/api/email-lists/{$offers->id}/subscribers", ['email' => 'SAM@example.com', 'consent_source' => 'manual'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', fn ($message) => str_contains($message, 'unsubscribed'));
        $this->assertTrue(app(SuppressionService::class)->isSuppressed('sam@example.com'));

        // Resubscribing needs a note, and lifts the suppression.
        $subscriber = EmailSubscriber::create(['email' => 'sam@example.com', 'consent_source' => 'manual', 'status' => 'unsubscribed']);
        $this->postJson("/api/email-subscribers/{$subscriber->id}/resubscribe", [])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson("/api/email-subscribers/{$subscriber->id}/resubscribe", ['note' => 'Emailed asking to rejoin'])
            ->assertOk()->assertJsonPath('data.status', 'subscribed');

        $this->assertFalse(app(SuppressionService::class)->isSuppressed('sam@example.com'));
        $this->assertSame('Emailed asking to rejoin', EmailSuppression::first()->lift_note);
        $this->postJson("/api/email-lists/{$offers->id}/subscribers", ['email' => 'sam@example.com', 'consent_source' => 'manual'])->assertCreated();
    }

    public function test_subscribers_can_be_searched_filtered_and_edited(): void
    {
        $this->actingAsRole('admin');
        $list = EmailList::create(['name' => 'Newsletter']);
        foreach (['sam@example.com' => 'Sam', 'alex@example.com' => 'Alex'] as $email => $name) {
            EmailSubscriber::create(['email' => $email, 'first_name' => $name, 'consent_source' => 'manual'])->lists()->attach($list->id, ['added_via' => 'manual']);
        }
        $alex = EmailSubscriber::firstWhere('email', 'alex@example.com');
        $alex->update(['status' => 'bounced']);

        $this->getJson("/api/email-lists/{$list->id}/subscribers?search=sam")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Sam');
        $this->getJson("/api/email-lists/{$list->id}/subscribers?status=bounced")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'alex@example.com');
        $this->getJson("/api/email-lists/{$list->id}")->assertJsonPath('data.subscribed_count', 1)->assertJsonPath('data.bounced_count', 1);

        $this->putJson("/api/email-subscribers/{$alex->id}", ['email' => 'SAM@example.com'])->assertUnprocessable();
        $this->putJson("/api/email-subscribers/{$alex->id}", ['email' => 'alex@example.com', 'first_name' => 'Alexandra'])->assertOk()->assertJsonPath('data.first_name', 'Alexandra');
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
