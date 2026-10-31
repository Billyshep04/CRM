<?php

namespace Tests\Feature;

use App\Mail\SeoReportMailable;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SeoReport;
use App\Models\User;
use App\Services\SeoReportPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeoReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_update_and_list_seo_reports(): void
    {
        $this->actingAsRole('staff');
        $customer = $this->customer('Harbourside Kitchens');

        $created = $this->postJson('/api/seo-reports', $this->payload($customer))
            ->assertCreated()
            ->assertJsonPath('data.report_number', 'SEO-HK-1026')
            ->assertJsonPath('data.website_domain', 'harbourside-kitchens.co.uk')
            ->json('data');

        $report = SeoReport::query()->findOrFail($created['id']);
        $this->assertSame('2026-10-01', $report->report_month->toDateString());
        // Blank rows from the form are dropped.
        $this->assertCount(1, $report->data['keywords']);
        $this->assertSame(['Refresh the utility room guide'], $report->data['priorities']);

        $payload = $this->payload($customer);
        $payload['report_number'] = 'SEO-CUSTOM-1';
        $this->putJson("/api/seo-reports/{$report->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.report_number', 'SEO-CUSTOM-1');

        $this->getJson("/api/seo-reports?customer_id={$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $report->id)
            ->assertJsonPath('data.0.customer.name', 'Harbourside Kitchens');
    }

    public function test_report_downloads_as_a_six_page_pdf(): void
    {
        $this->actingAsRole('staff');
        $customer = $this->customer('Harbourside Kitchens');
        $report = SeoReport::query()->create([
            'customer_id' => $customer->id,
            'report_number' => 'SEO-HK-1026',
            'report_month' => '2026-10-01',
            'issue_date' => '2026-11-03',
            'website_domain' => 'harbourside-kitchens.co.uk',
            'data' => $this->payload($customer)['data'],
        ]);

        $response = $this->get("/api/seo-reports/{$report->id}/download");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('SEO-Report-SEO-HK-1026.pdf', (string) $response->headers->get('content-disposition'));
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(6, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }

    public function test_an_almost_empty_report_still_renders(): void
    {
        $this->actingAsRole('staff');
        $customer = $this->customer('Bare Minimum Ltd');

        $response = $this->post('/api/seo-reports/preview', [
            'customer_id' => $customer->id,
            'report_month' => '2026-01',
            'issue_date' => '2026-02-02',
            'website_domain' => 'https://www.example.co.uk/',
            'data' => ['summary' => null],
        ]);

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_figures_derived_for_the_pdf(): void
    {
        $customer = $this->customer('Harbourside Kitchens');
        $report = new SeoReport([
            'customer_id' => $customer->id,
            'report_number' => 'SEO-HK-1026',
            'report_month' => '2026-10-01',
            'issue_date' => '2026-11-03',
            'website_domain' => 'harbourside-kitchens.co.uk',
            'data' => $this->payload($customer)['data'],
        ]);

        $view = app(SeoReportPdfService::class)->viewData($report);

        $this->assertSame('October 2026 SEO Report', $view['title']);
        $this->assertSame('September 2026', $view['previousMonthLabel']);
        $this->assertSame('6 of 12', $view['campaign']);
        $this->assertSame(['tone' => 'up', 'arrow' => '▲', 'text' => '18.4%'], $view['cards']['sessions']['change']);
        $this->assertSame('118.4k', $view['cards']['impressions']['value']);
        $this->assertSame('3.1 places', $view['cards']['position']['change']['text']);
        // Keywords in top 10 = positions 1–3 plus 4–10, this month and last.
        $this->assertSame('37', $view['cards']['topTen']['value']);
        $this->assertSame('28', $view['cards']['topTen']['was']);
        $this->assertSame(64.0, $view['trackedKeywords']);
        $this->assertSame('2.7%', $view['searchConsole'][2]['current']);
        $this->assertSame('−0.2 pts', $view['searchConsole'][2]['change']['text']);
        $this->assertSame('Sep', $view['chart'][10]['label']);
        $this->assertSame('Oct', $view['chart'][11]['label']);
        $this->assertSame('New', $view['landingPages'][1]['change']['text']);
        $this->assertSame('▼ 2', $view['keywords'][0]['change']['text']);
        $this->assertSame('Needs work', $view['vitals']['rows'][2]['ratingLabel']);
        $this->assertSame('Not passed', $view['vitals']['status']);
        $this->assertSame('+2 since last month', $view['authority'][0]['change']['text']);
    }

    public function test_customers_cannot_use_the_staff_seo_report_api(): void
    {
        $this->actingAsRole('customer');

        $this->getJson('/api/seo-reports')->assertForbidden();
    }

    public function test_customers_see_and_download_only_their_own_reports_in_the_portal(): void
    {
        $user = $this->actingAsRole('customer');
        $own = $this->customer('Harbourside Kitchens');
        $own->forceFill(['user_id' => $user->id])->save();
        $other = $this->customer('Someone Else');

        $ownReport = $this->storedReport($own, 'SEO-HK-1026');
        $otherReport = $this->storedReport($other, 'SEO-SE-1026');

        $this->getJson('/api/portal/seo-reports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownReport->id)
            ->assertJsonMissingPath('data.0.data');

        $download = $this->get("/api/portal/seo-reports/{$ownReport->id}/download");
        $download->assertOk();
        $this->assertStringStartsWith('%PDF', $download->getContent());
        $this->assertStringStartsWith('attachment', (string) $download->headers->get('content-disposition'));

        $inline = $this->get("/api/portal/seo-reports/{$ownReport->id}/download?inline=1");
        $inline->assertOk();
        $this->assertStringStartsWith('inline', (string) $inline->headers->get('content-disposition'));

        $this->get("/api/portal/seo-reports/{$otherReport->id}/download")->assertNotFound();
    }

    public function test_saving_a_report_never_emails_the_customer(): void
    {
        Mail::fake();
        $this->actingAsRole('admin');
        $customer = $this->customer('Harbourside Kitchens');

        $id = $this->postJson('/api/seo-reports', $this->payload($customer))->assertCreated()->json('data.id');
        $this->putJson("/api/seo-reports/{$id}", $this->payload($customer))->assertOk();

        Mail::assertNothingSent();
        $this->assertNull(SeoReport::query()->findOrFail($id)->sent_at);
    }

    public function test_admin_can_send_a_report_to_the_client_contact(): void
    {
        Mail::fake();
        $this->actingAsRole('admin');
        $customer = $this->customer('Harbourside Kitchens');
        $report = $this->storedReport($customer, 'SEO-HK-1026');
        $report->forceFill(['data' => [...$report->data, 'contact_email' => 'sarah@harbourside-kitchens.co.uk']])->save();

        $this->postJson("/api/seo-reports/{$report->id}/send")
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'sarah@harbourside-kitchens.co.uk');

        Mail::assertSent(SeoReportMailable::class, function (SeoReportMailable $mail): bool {
            return $mail->hasTo('sarah@harbourside-kitchens.co.uk')
                && str_starts_with($mail->pdf, '%PDF')
                && $mail->subjectLine === 'Your October 2026 SEO report for example.co.uk';
        });
        $this->assertNotNull($report->fresh()->sent_at);
    }

    public function test_send_falls_back_to_the_customer_email(): void
    {
        Mail::fake();
        $this->actingAsRole('admin');
        $customer = $this->customer('Harbourside Kitchens');
        $report = $this->storedReport($customer, 'SEO-HK-1026');

        $this->postJson("/api/seo-reports/{$report->id}/send")->assertOk();

        Mail::assertSent(SeoReportMailable::class, fn (SeoReportMailable $mail): bool => $mail->hasTo('hello@harboursidekitchens.co.uk'));
    }

    public function test_staff_cannot_send_reports(): void
    {
        Mail::fake();
        $this->actingAsRole('staff');
        $report = $this->storedReport($this->customer('Harbourside Kitchens'), 'SEO-HK-1026');

        $this->postJson("/api/seo-reports/{$report->id}/send")->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_required_fields_are_validated_with_readable_names(): void
    {
        $this->actingAsRole('staff');
        $customer = $this->customer('Harbourside Kitchens');
        $payload = $this->payload($customer);
        $payload['data']['keywords'][0]['current'] = 0;

        $this->postJson('/api/seo-reports', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors', fn (array $errors) => str_contains(reset($errors)[0], "this month's keyword position"));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'report_month' => '2026-10',
            'issue_date' => '2026-11-03',
            'website_domain' => 'https://www.harbourside-kitchens.co.uk/',
            'data' => [
                'contact_name' => 'Sarah Collins',
                'campaign_month' => 6,
                'campaign_length' => 12,
                'summary' => 'October was the strongest month since the campaign began.',
                'metrics' => [
                    'sessions' => ['current' => 4862, 'previous' => 4107],
                    'clicks' => ['current' => 3214, 'previous' => 2790],
                    'impressions' => ['current' => 118420, 'previous' => 96510],
                    'avg_position' => ['current' => 14.2, 'previous' => 17.3],
                    'enquiries' => ['current' => 58, 'previous' => 46],
                ],
                'key_wins' => [['title' => 'Bespoke kitchens Devon reached position 3', 'detail' => 'Up from 11.'], ['title' => null, 'detail' => null]],
                'traffic_history' => [2140, 2190, 2320, null, 2490, 2600, 2790, 2990, 3200, 3540],
                'growth_since_start' => 127,
                'landing_pages' => [
                    ['path' => '/bespoke-kitchens/', 'sessions' => 1284, 'previous' => 911],
                    ['path' => '/kitchen-design-service/', 'sessions' => 447, 'previous' => null],
                ],
                'ranking_buckets' => [
                    'top3' => ['current' => 12, 'previous' => 8],
                    'top10' => ['current' => 25, 'previous' => 20],
                    'top20' => ['current' => 16, 'previous' => 19],
                    'top50' => ['current' => 11, 'previous' => 17],
                ],
                'keywords' => [
                    ['keyword' => 'utility room design', 'searches' => 720, 'previous' => 15, 'current' => 17],
                    ['keyword' => null, 'searches' => null, 'previous' => null, 'current' => null],
                ],
                'health' => ['current' => 86, 'previous' => 72],
                'vitals' => ['lcp' => 2.2, 'inp' => 160, 'cls' => 0.14],
                'fixes' => [['count' => 12, 'label' => 'Broken links repaired']],
                'authority' => ['domain_authority' => ['current' => 24, 'previous' => 22]],
                'new_links' => [['domain' => 'devonhomesmagazine.co.uk', 'authority' => 41, 'type' => 'Editorial feature']],
                'work_items' => [['category' => 'Content', 'title' => 'Published the kitchen design page', 'detail' => '1,200 words.']],
                'priorities' => ['Refresh the utility room guide', null, ''],
            ],
        ];
    }

    private function storedReport(Customer $customer, string $number): SeoReport
    {
        return SeoReport::query()->create([
            'customer_id' => $customer->id,
            'report_number' => $number,
            'report_month' => '2026-10-01',
            'issue_date' => '2026-11-03',
            'website_domain' => 'example.co.uk',
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
