<?php

namespace Tests\Feature;

use App\Contracts\AnalyticsProvider;
use App\Jobs\SyncWebsiteAnalytics;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAnalyticsSnapshot;
use App\Services\Analytics\GoogleServiceAccountToken;
use App\Services\Analytics\WebsiteAnalyticsSync;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use phpseclib3\Crypt\RSA;

class WebsiteAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow('2026-09-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_connect_verifies_access_enables_reporting_and_queues_a_backfill(): void
    {
        Bus::fake();
        $admin = $this->user('admin');
        $website = $this->website($this->customer());

        $this->actingAs($admin)
            ->postJson("/api/websites/{$website->id}/analytics/connect", [
                'property_id' => 'properties/123456789',
                'dashboard_url' => 'https://analytics.google.com/x',
            ])
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.status', 'connected')
            ->assertJsonPath('data.property_id', '123456789');

        $website->refresh();
        $this->assertTrue($website->google_analytics_enabled);
        $this->assertSame('connected', $website->google_analytics_status);
        $this->assertSame('https://analytics.google.com/x', $website->google_analytics_dashboard_url);
        $this->assertDatabaseHas('website_activities', ['website_id' => $website->id, 'type' => 'analytics']);

        Bus::assertDispatched(SyncWebsiteAnalytics::class, fn (SyncWebsiteAnalytics $job) => $job->websiteId === $website->id && $job->mode === 'backfill');
    }

    public function test_connect_rejects_a_property_the_service_account_cannot_read(): void
    {
        Bus::fake();
        $this->mock(AnalyticsProvider::class, function ($mock): void {
            $mock->shouldReceive('verifyAccess')->andReturn(false);
        });
        $admin = $this->user('admin');
        $website = $this->website($this->customer());

        $this->actingAs($admin)
            ->postJson("/api/websites/{$website->id}/analytics/connect", ['property_id' => '999999999'])
            ->assertStatus(422)
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.status', 'no_access');

        $this->assertFalse($website->fresh()->google_analytics_enabled);
        Bus::assertNotDispatched(SyncWebsiteAnalytics::class);
    }

    public function test_connect_rejects_a_non_numeric_property_id(): void
    {
        $admin = $this->user('admin');
        $website = $this->website($this->customer());

        $this->actingAs($admin)
            ->postJson("/api/websites/{$website->id}/analytics/connect", ['property_id' => 'not-a-property'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('property_id');
    }

    public function test_backfill_job_populates_daily_and_month_snapshots_and_is_idempotent(): void
    {
        config(['analytics.backfill_days' => 40]);
        Http::fake();
        $website = $this->website($this->customer(), [
            'google_analytics_property_id' => '123456789',
            'google_analytics_enabled' => true,
        ]);

        $run = fn () => (new SyncWebsiteAnalytics($website->id, 'backfill'))->handle(app(WebsiteAnalyticsSync::class));
        $run();
        $firstDailyCount = WebsiteAnalyticsSnapshot::where('granularity', 'day')->count();
        $run();

        $this->assertSame(40, $firstDailyCount);
        $this->assertSame($firstDailyCount, WebsiteAnalyticsSnapshot::where('granularity', 'day')->count(), 'Re-running must upsert, not duplicate.');
        $this->assertGreaterThanOrEqual(2, WebsiteAnalyticsSnapshot::where('granularity', 'month')->count());

        $month = WebsiteAnalyticsSnapshot::where('granularity', 'month')->latest('period_start')->first();
        $this->assertGreaterThan(0, $month->sessions);
        $this->assertGreaterThan(0, $month->dimensionRows()->where('dimension', 'page_path')->count());

        $website->refresh();
        $this->assertSame('connected', $website->google_analytics_status);
        $this->assertNotNull($website->google_analytics_last_synced_at);
        Http::assertNothingSent();
    }

    public function test_job_is_a_no_op_when_analytics_is_not_configured(): void
    {
        $website = $this->website($this->customer(), ['google_analytics_property_id' => '123', 'google_analytics_enabled' => false]);

        (new SyncWebsiteAnalytics($website->id, 'backfill'))->handle(app(WebsiteAnalyticsSync::class));

        $this->assertSame(0, WebsiteAnalyticsSnapshot::count());
    }

    public function test_summary_endpoint_is_derived_from_stored_snapshots_with_no_outbound_calls(): void
    {
        Http::fake();
        $admin = $this->user('admin');
        $website = $this->website($this->customer(), [
            'google_analytics_property_id' => '123456789',
            'google_analytics_enabled' => true,
            'google_analytics_status' => 'connected',
        ]);

        // Current 28d window averages 100/day; the prior 28d window averages 50/day.
        $this->seedDailySnapshots($website, Carbon::yesterday()->subDays(27), Carbon::yesterday(), 100);
        $this->seedDailySnapshots($website, Carbon::yesterday()->subDays(55), Carbon::yesterday()->subDays(28), 50);

        $response = $this->actingAs($admin)
            ->getJson("/api/websites/{$website->id}/analytics?range=28d")
            ->assertOk();

        $response->assertJsonPath('data.status', 'connected');
        $response->assertJsonPath('data.totals.sessions', 2800);
        $response->assertJsonPath('data.previous_totals.sessions', 1400);
        $this->assertEqualsWithDelta(100, $response->json('data.deltas.sessions.percent'), 0.001);
        $this->assertCount(28, $response->json('data.series'));

        Http::assertNothingSent();
    }

    public function test_summary_reports_unconfigured_for_a_website_without_a_property(): void
    {
        $admin = $this->user('admin');
        $website = $this->website($this->customer());

        $this->actingAs($admin)
            ->getJson("/api/websites/{$website->id}/analytics")
            ->assertOk()
            ->assertJsonPath('data.status', 'unconfigured');
    }

    public function test_website_resource_exposes_an_analytics_block_to_staff(): void
    {
        $admin = $this->user('admin');
        $website = $this->website($this->customer(), [
            'google_analytics_property_id' => '123456789',
            'google_analytics_enabled' => true,
            'google_analytics_status' => 'connected',
        ]);

        $this->actingAs($admin)
            ->getJson("/api/websites/{$website->id}")
            ->assertOk()
            ->assertJsonPath('data.analytics.enabled', true)
            ->assertJsonPath('data.analytics.status', 'connected');
    }

    public function test_portal_only_shows_traffic_when_visibility_is_enabled_and_never_leaks_internal_fields(): void
    {
        $portalUser = $this->user('customer', 'client@example.com');
        $customer = $this->customer($portalUser, 'client@example.com');
        $website = $this->website($customer, [
            'google_analytics_property_id' => '123456789',
            'google_analytics_enabled' => true,
            'google_analytics_status' => 'connected',
            'google_analytics_last_error' => 'internal detail should never surface',
        ]);
        $this->seedDailySnapshots($website, Carbon::yesterday()->subDays(27), Carbon::yesterday(), 30);

        // Hidden by default.
        $this->actingAs($portalUser)
            ->getJson("/api/portal/websites/{$website->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.analytics');

        $website->update(['portal_visibility' => [...Website::defaultPortalVisibility(), 'analytics' => true]]);

        $body = $this->actingAs($portalUser)
            ->getJson("/api/portal/websites/{$website->id}")
            ->assertOk()
            ->assertJsonPath('data.analytics.totals.sessions', 840)
            ->json('data.analytics');

        $this->assertArrayNotHasKey('property_id', $body);
        $this->assertArrayNotHasKey('last_error', $body);
        $this->assertStringNotContainsString('internal detail', json_encode($body));
    }

    public function test_sync_command_only_queues_websites_with_a_linked_property(): void
    {
        Bus::fake();
        $customer = $this->customer();
        $linked = $this->website($customer, ['google_analytics_property_id' => '111', 'google_analytics_enabled' => true]);
        $this->website($customer, ['name' => 'No GA', 'domain' => 'nога.example.com', 'login_url' => 'https://noga.example.com']);
        $this->website($customer, ['name' => 'Disabled', 'domain' => 'off.example.com', 'login_url' => 'https://off.example.com', 'google_analytics_property_id' => '222', 'google_analytics_enabled' => false]);

        $this->artisan('analytics:sync-websites --mode=recent')->assertSuccessful();

        Bus::assertDispatchedTimes(SyncWebsiteAnalytics::class, 1);
        Bus::assertDispatched(SyncWebsiteAnalytics::class, fn (SyncWebsiteAnalytics $job) => $job->websiteId === $linked->id && $job->mode === 'recent');
    }

    public function test_google_provider_maps_a_ga4_batch_response_and_the_service_account_token_is_cached(): void
    {
        Cache::flush();
        $key = RSA::createKey(2048);
        config([
            'analytics.driver' => 'google',
            'analytics.google.credentials_json' => json_encode([
                'client_email' => 'reporting@project.iam.gserviceaccount.com',
                'private_key' => (string) $key,
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ]),
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'analyticsdata.googleapis.com/*' => Http::sequence()
                ->push(['reports' => [
                    $this->timeReport(),
                    $this->breakdownReport([['Home', 500], ['/about', 120]]),
                    $this->breakdownReport([['Organic Search', 400]]),
                ]])
                ->push(['reports' => [
                    $this->breakdownReport([['United Kingdom', 450]]),
                    $this->breakdownReport([['mobile', 380]]),
                    $this->eventsReport([['form_submit', 40, 40], ['scroll', 900, 0]]),
                ]]),
        ]);

        $provider = app(AnalyticsProvider::class);
        $report = $provider->fetchReport('123456789', '2026-09-01', '2026-09-02');

        $this->assertSame(300, $report->total('sessions'));
        $this->assertSame([
            ['date' => '2026-09-01', 'sessions' => 100, 'total_users' => 80, 'screen_page_views' => 200, 'conversions' => 5],
            ['date' => '2026-09-02', 'sessions' => 200, 'total_users' => 150, 'screen_page_views' => 420, 'conversions' => 9],
        ], $report->series);
        $this->assertSame('Home', $report->dimensions['page_path'][0]['value']);
        $this->assertSame(['form_submit' => 40], $report->keyEvents);

        // Token minted once, then served from cache.
        app(GoogleServiceAccountToken::class)->accessToken();
        Http::assertSentCount(3); // 1 token + 2 batch calls; no second token request.
    }

    // --- helpers -----------------------------------------------------------

    private function user(string $role, ?string $email = null): User
    {
        $user = User::factory()->create($email ? ['email' => $email] : []);
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }

    private function customer(?User $user = null, string $email = 'customer@example.com'): Customer
    {
        return Customer::create(['name' => 'Customer', 'email' => $email, 'billing_address' => '1 Test Road', 'user_id' => $user?->id]);
    }

    private function website(Customer $customer, array $extra = []): Website
    {
        return Website::create([...[
            'customer_id' => $customer->id,
            'name' => 'Example',
            'domain' => 'example.com',
            'login_url' => 'https://example.com',
            'management_enabled' => true,
            'portal_visibility' => Website::defaultPortalVisibility(),
        ], ...$extra]);
    }

    private function seedDailySnapshots(Website $website, Carbon $start, Carbon $end, int $perDay): void
    {
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            WebsiteAnalyticsSnapshot::create([
                'website_id' => $website->id,
                'property_id' => (string) $website->google_analytics_property_id,
                'granularity' => 'day',
                'period_start' => $date->toDateString(),
                'period_end' => $date->toDateString(),
                'sessions' => $perDay,
                'total_users' => $perDay,
                'screen_page_views' => $perDay * 2,
                'conversions' => (int) round($perDay * 0.05),
                'fetched_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function timeReport(): array
    {
        $metrics = ['sessions', 'totalUsers', 'newUsers', 'engagedSessions', 'screenPageViews', 'userEngagementDuration', 'engagementRate', 'bounceRate', 'eventCount', 'keyEvents'];

        return [
            'metricHeaders' => array_map(fn ($m) => ['name' => $m], $metrics),
            'rows' => [
                ['dimensionValues' => [['value' => '20260901']], 'metricValues' => $this->values([100, 80, 60, 55, 200, 5500, 0.55, 0.45, 210, 5])],
                ['dimensionValues' => [['value' => '20260902']], 'metricValues' => $this->values([200, 150, 110, 120, 420, 12000, 0.6, 0.4, 440, 9])],
            ],
            'totals' => [['metricValues' => $this->values([300, 230, 170, 175, 620, 17500, 0.58, 0.42, 650, 14])]],
        ];
    }

    /**
     * @param  list<array{0: string, 1: int}>  $rows
     * @return array<string, mixed>
     */
    private function breakdownReport(array $rows): array
    {
        return [
            'rows' => array_map(fn ($row) => [
                'dimensionValues' => [['value' => $row[0]]],
                'metricValues' => $this->values([$row[1], (int) round($row[1] * 0.8), $row[1] * 2, (int) round($row[1] * 0.05)]),
            ], $rows),
        ];
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int}>  $rows
     * @return array<string, mixed>
     */
    private function eventsReport(array $rows): array
    {
        return [
            'rows' => array_map(fn ($row) => [
                'dimensionValues' => [['value' => $row[0]]],
                'metricValues' => $this->values([$row[1], $row[2]]),
            ], $rows),
        ];
    }

    /**
     * @param  list<int|float>  $values
     * @return list<array{value: string}>
     */
    private function values(array $values): array
    {
        return array_map(fn ($value) => ['value' => (string) $value], $values);
    }
}
