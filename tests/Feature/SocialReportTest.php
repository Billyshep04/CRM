<?php

namespace Tests\Feature;

use App\Mail\SocialReportMailable;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SocialReport;
use App\Models\User;
use App\Services\SocialReportPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_update_and_list_social_reports(): void
    {
        $this->actingAsRole('staff');
        $customer = $this->customer('Harbourside Kitchens');

        $created = $this->postJson('/api/social-reports', $this->payload($customer))
            ->assertCreated()
            ->assertJsonPath('data.report_number', 'SM-HK-1026')
            ->json('data');

        $report = SocialReport::query()->findOrFail($created['id']);
        $this->assertSame('2026-10-01', $report->report_month->toDateString());
        // Channels are kept in report order, and blank rows from the form are dropped.
        $this->assertSame(['instagram', 'youtube'], $report->channels());
        $this->assertCount(1, $report->data['platforms']['instagram']['top_posts']);

        $payload = $this->payload($customer);
        $payload['report_number'] = 'SM-CUSTOM-1';
        $this->putJson("/api/social-reports/{$report->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.report_number', 'SM-CUSTOM-1');

        $this->getJson("/api/social-reports?customer_id={$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $report->id)
            ->assertJsonPath('data.0.customer.name', 'Harbourside Kitchens');
    }

    public function test_a_report_needs_at_least_one_channel(): void
    {
        $this->actingAsRole('staff');
        $payload = $this->payload($this->customer('Harbourside Kitchens'));
        $payload['data']['channels'] = [];

        $this->postJson('/api/social-reports', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors', fn (array $errors) => reset($errors)[0] === 'Tick at least one social media channel.');
    }

    public function test_the_pdf_has_a_page_for_each_selected_channel(): void
    {
        $this->actingAsRole('staff');
        $customer = $this->customer('Harbourside Kitchens');
        $report = $this->storedReport($customer, 'SM-HK-1026');

        $response = $this->get("/api/social-reports/{$report->id}/download");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('Social-Media-Report-SM-HK-1026.pdf', (string) $response->headers->get('content-disposition'));
        // Cover, overall performance, Instagram and YouTube.
        $this->assertSame(4, preg_match_all('/\/Type\s*\/Page[^s]/', $response->getContent()));

        $data = $report->data;
        $data['channels'] = ['instagram', 'facebook', 'threads', 'linkedin', 'x', 'youtube'];
        $report->update(['data' => $data]);
        $this->assertSame(8, preg_match_all('/\/Type\s*\/Page[^s]/', $this->get("/api/social-reports/{$report->id}/download")->getContent()));
    }

    public function test_an_almost_empty_report_still_renders(): void
    {
        $this->actingAsRole('staff');

        $response = $this->post('/api/social-reports/preview', [
            'customer_id' => $this->customer('Bare Minimum Ltd')->id,
            'report_month' => '2026-01',
            'issue_date' => '2026-02-02',
            'data' => ['channels' => ['x']],
        ]);

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_figures_derived_for_the_pdf(): void
    {
        $customer = $this->customer('Harbourside Kitchens');
        $report = new SocialReport([
            'customer_id' => $customer->id,
            'report_number' => 'SM-HK-1026',
            'report_month' => '2026-10-01',
            'issue_date' => '2026-11-03',
            'data' => $this->payload($customer)['data'],
        ]);

        $view = app(SocialReportPdfService::class)->viewData($report);
        $instagram = $view['pages'][0];
        $youtube = $view['pages'][1];

        $this->assertSame('October 2026 Social Media Report', $view['title']);
        $this->assertSame(4, $view['totalPages']);
        $this->assertSame('Both platforms combined, 01 Oct – 31 Oct 2026 compared with September 2026', $view['overviewSubtitle']);
        // Totals add up the selected channels only.
        $this->assertSame('6,060', $view['glance']['followers']['value']);
        $this->assertSame('+506 (9.1%)', $view['glance']['followers']['change']['text']);
        $this->assertSame('96.8k', $view['glance']['reach']['value']);
        $this->assertSame('+0.6 pts', $view['glance']['engagementRate']['change']['text']);
        $this->assertSame('24', $view['cards'][4]['value']);
        $this->assertSame('3', $view['cards'][4]['change']['text']);
        $this->assertSame(['Instagram', 'YouTube'], array_column($view['share'], 'name'));
        $this->assertSame('+412 · 9.1%', $view['comparison'][0]['growth']['text']);

        $this->assertSame('412 · 9.1%', $instagram['cards'][0]['change']['text']);
        $this->assertSame('82.4k', $instagram['cards'][1]['value']);
        $this->assertSame('0.9 pts', $instagram['cards'][2]['change']['text']);
        $this->assertSame('412', $instagram['chart'][5]['value']);
        $this->assertSame('Oct', $instagram['chart'][5]['label']);
        $this->assertSame('May', $instagram['chart'][0]['label']);
        $this->assertSame('1,042', $instagram['audience'][3]['value']);
        $this->assertSame('18.2k', $instagram['topPosts'][0]['stat']);

        $this->assertSame('Subscribers', $youtube['cards'][0]['label']);
        $this->assertSame('612 hrs', $youtube['cards'][2]['value']);
        $this->assertSame('3:42', $youtube['cards'][3]['value']);
        $this->assertSame(['tone' => 'up', 'arrow' => '▲', 'text' => '0:24'], $youtube['cards'][3]['change']);
        $this->assertSame('videos', $youtube['postsWord']);
    }

    public function test_top_post_images_are_cropped_stored_and_embedded(): void
    {
        Storage::fake('local');
        $this->actingAsRole('staff');

        $path = $this->post('/api/social-reports/images', ['image' => UploadedFile::fake()->image('post.jpg', 1080, 1080)])
            ->assertCreated()
            ->json('data.path');

        $this->assertMatchesRegularExpression(SocialReport::IMAGE_PATH_PATTERN, $path);
        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame([900, 315], [$width, $height]);

        $name = basename($path);
        $this->get("/api/social-reports/images/{$name}")->assertOk()->assertHeader('content-type', 'image/jpeg');

        $customer = $this->customer('Harbourside Kitchens');
        $payload = $this->payload($customer);
        $payload['data']['platforms']['instagram']['top_posts'][0]['image'] = $path;
        $id = $this->postJson('/api/social-reports', $payload)->assertCreated()->json('data.id');
        $view = app(SocialReportPdfService::class)->viewData(SocialReport::query()->findOrFail($id));
        $this->assertStringStartsWith('data:image/jpeg;base64,', $view['pages'][0]['topPosts'][0]['image']);

        // Only paths the upload created are accepted, so a report can't read other files.
        $payload['data']['platforms']['instagram']['top_posts'][0]['image'] = '../../.env';
        $this->postJson('/api/social-reports', $payload)->assertUnprocessable();
    }

    public function test_saving_a_report_never_emails_the_customer(): void
    {
        Mail::fake();
        $this->actingAsRole('admin');
        $customer = $this->customer('Harbourside Kitchens');

        $id = $this->postJson('/api/social-reports', $this->payload($customer))->assertCreated()->json('data.id');
        $this->putJson("/api/social-reports/{$id}", $this->payload($customer))->assertOk();

        Mail::assertNothingSent();
        $this->assertNull(SocialReport::query()->findOrFail($id)->sent_at);
    }

    public function test_admin_can_send_a_report_but_staff_cannot(): void
    {
        Mail::fake();
        $report = $this->storedReport($this->customer('Harbourside Kitchens'), 'SM-HK-1026');

        $this->actingAsRole('staff');
        $this->postJson("/api/social-reports/{$report->id}/send")->assertForbidden();
        Mail::assertNothingSent();

        $this->actingAsRole('admin');
        $this->postJson("/api/social-reports/{$report->id}/send")
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'sarah@harbourside-kitchens.co.uk');

        Mail::assertSent(SocialReportMailable::class, function (SocialReportMailable $mail): bool {
            return $mail->hasTo('sarah@harbourside-kitchens.co.uk')
                && str_starts_with($mail->pdf, '%PDF')
                && $mail->subjectLine === 'Your October 2026 social media report';
        });
        $this->assertNotNull($report->fresh()->sent_at);
    }

    public function test_customers_see_and_download_only_their_own_reports_in_the_portal(): void
    {
        $user = $this->actingAsRole('customer');
        $own = $this->customer('Harbourside Kitchens');
        $own->forceFill(['user_id' => $user->id])->save();
        $ownReport = $this->storedReport($own, 'SM-HK-1026');
        $otherReport = $this->storedReport($this->customer('Someone Else'), 'SM-SE-1026');

        $this->getJson('/api/social-reports')->assertForbidden();

        $this->getJson('/api/portal/social-reports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownReport->id)
            ->assertJsonPath('data.0.channels', ['Instagram', 'YouTube'])
            ->assertJsonMissingPath('data.0.data');

        $inline = $this->get("/api/portal/social-reports/{$ownReport->id}/download?inline=1");
        $inline->assertOk();
        $this->assertStringStartsWith('inline', (string) $inline->headers->get('content-disposition'));

        $this->get("/api/portal/social-reports/{$otherReport->id}/download")->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Customer $customer): array
    {
        $pair = fn ($current, $previous) => ['current' => $current, 'previous' => $previous];

        return [
            'customer_id' => $customer->id,
            'report_month' => '2026-10',
            'issue_date' => '2026-11-03',
            'data' => [
                'contact_name' => 'Sarah Collins',
                'contact_email' => 'sarah@harbourside-kitchens.co.uk',
                'summary' => 'The audience grew by 506 followers.',
                'channels' => ['youtube', 'instagram'],
                'totals' => ['engagements' => $pair(8940, 7210), 'engagement_rate' => $pair(4.8, 4.2), 'website_clicks' => $pair(1126, 944)],
                'platforms' => [
                    'instagram' => [
                        'handle' => '@harbourside.kitchens',
                        'posts' => $pair(18, 15),
                        'metrics' => ['followers' => $pair(4920, 4508), 'reach' => $pair(82400, 59624), 'engagement_rate' => $pair(5.6, 4.7), 'highlight' => $pair(2318, 1819)],
                        'history' => [160, 210, 230, 210, 248],
                        'audience' => ['North Devon', '35–44', 'Sunday', '1042'],
                        'formats' => [['label' => 'Reels', 'posts' => 8, 'average' => 7140, 'engagement' => 6.8], ['label' => null]],
                        'top_posts' => [
                            ['title' => 'Before & after: a 1970s kitchen', 'stat' => 18200, 'metric' => 1204, 'metric_label' => 'likes'],
                            ['title' => null, 'stat' => null],
                        ],
                        'insight' => 'Reels reached more than twice as many people.',
                    ],
                    'youtube' => [
                        'handle' => '@HarboursideKitchens',
                        'posts' => $pair(6, 6),
                        'metrics' => ['followers' => $pair(1140, 1046), 'reach' => $pair(14400, 10000), 'engagement_rate' => $pair(4.9, 4.5), 'watch_time' => $pair(612, 442)],
                        'avg_duration' => ['current' => '3:42', 'previous' => '3:18'],
                        'history' => [41, 48, 55, 62, 71],
                    ],
                    // Unticked channels keep their figures but get no page.
                    'facebook' => ['metrics' => ['followers' => $pair(3410, 3282)]],
                ],
            ],
        ];
    }

    private function storedReport(Customer $customer, string $number): SocialReport
    {
        return SocialReport::query()->create([
            'customer_id' => $customer->id,
            'report_number' => $number,
            'report_month' => '2026-10-01',
            'issue_date' => '2026-11-03',
            'data' => $this->payload($customer)['data'],
        ]);
    }

    private function customer(string $name): Customer
    {
        return Customer::query()->create([
            'name' => $name,
            'email' => 'hello@'.strtolower(str_replace(' ', '', $name)).'.co.uk',
            'billing_address' => '1 Harbour Road, Barnstaple',
        ]);
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
