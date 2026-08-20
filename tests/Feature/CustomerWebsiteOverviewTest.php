<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthCheck;
use App\Services\Websites\SslCertificateInspector;
use App\Services\Websites\WebsiteMonitor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Mockery\MockInterface;
use Tests\TestCase;

class CustomerWebsiteOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_ssl_states_are_customer_friendly_and_affect_overall_health(): void
    {
        [$user, $website] = $this->portalWebsite();
        $this->external($website, ['ssl_status' => 'valid', 'ssl_days_remaining' => 63, 'ssl_expires_at' => now()->addDays(63)]);
        $this->actingAs($user)->getJson("/api/portal/websites/{$website->id}")->assertOk()->assertJsonPath('data.ssl.label', 'Secure')->assertJsonPath('data.status', 'healthy');

        $this->external($website, ['ssl_status' => 'expiring', 'ssl_days_remaining' => 9, 'ssl_expires_at' => now()->addDays(9)]);
        $this->actingAs($user)->getJson("/api/portal/websites/{$website->id}")->assertJsonPath('data.ssl.label', 'Needs attention')->assertJsonPath('data.status', 'attention');

        $this->external($website, ['ssl_status' => 'invalid', 'ssl_error_reason' => 'certificate_invalid']);
        $this->actingAs($user)->getJson("/api/portal/websites/{$website->id}")->assertJsonPath('data.ssl.label', 'Problem detected')->assertJsonPath('data.status', 'critical')->assertJsonMissing(['ssl_error_reason' => 'certificate_invalid']);
    }

    public function test_ssl_check_failure_is_stored_safely_and_does_not_crash(): void
    {
        [, $website] = $this->portalWebsite();
        config(['website-audits.enforce_public_networks' => false]);
        Http::fake(['https://example.com*' => Http::response('OK')]);
        $this->mock(SslCertificateInspector::class, function (MockInterface $mock): void {
            $mock->shouldReceive('inspect')->once()->andReturn(['status' => 'unavailable', 'expires_at' => null, 'days_remaining' => null, 'error_reason' => 'timeout']);
        });
        app(WebsiteMonitor::class)->check($website, 'manual');
        $this->assertDatabaseHas('website_health_checks', ['website_id' => $website->id, 'ssl_status' => 'unavailable', 'ssl_error_reason' => 'timeout']);
    }

    public function test_agent_check_does_not_claim_the_site_is_online_and_updates_maintenance(): void
    {
        [$user, $website] = $this->portalWebsite(['wordpress_enabled' => true, 'agent_token_hash' => hash('sha256', 'token'), 'agent_token_encrypted' => 'token']);
        $this->withToken('token')->postJson("/api/website-agent/{$website->id}/status", ['plugin_count' => 9, 'plugin_updates' => 3, 'theme_updates' => 0])->assertAccepted();
        $this->assertDatabaseHas('website_health_checks', ['website_id' => $website->id, 'check_type' => 'agent', 'uptime_status' => 'unknown']);
        $this->actingAs($user)->getJson("/api/portal/websites/{$website->id}")->assertJsonPath('data.availability', 'Status temporarily unavailable')->assertJsonPath('data.maintenance.label', 'Maintenance scheduled')->assertJsonPath('data.maintenance.plugin_updates', 3);
    }

    public function test_insufficient_uptime_history_is_not_presented_as_a_percentage(): void
    {
        [$user, $website] = $this->portalWebsite();
        $this->external($website);
        $this->external($website, ['uptime_status' => 'offline']);
        $this->actingAs($user)->getJson("/api/portal/websites/{$website->id}")->assertJsonPath('data.uptime.reliable', false)->assertJsonPath('data.uptime.percent_30d', null)->assertJsonPath('data.uptime.label', 'Monitoring started recently');
    }

    public function test_repeated_external_failures_display_offline(): void
    {
        [$user, $website] = $this->portalWebsite(['consecutive_failures' => 1]);
        config(['website-audits.enforce_public_networks' => false]);
        Http::fake(fn () => throw new ConnectionException('Connection failed'));
        $this->mock(SslCertificateInspector::class, function (MockInterface $mock): void {
            $mock->shouldReceive('inspect')->once()->andReturn(['status' => 'unavailable', 'expires_at' => null, 'days_remaining' => null, 'error_reason' => 'timeout']);
        });
        app(WebsiteMonitor::class)->check($website, 'http');
        $this->actingAs($user)->getJson("/api/portal/websites/{$website->id}")->assertJsonPath('data.availability', 'Offline')->assertJsonPath('data.status', 'critical');
    }

    public function test_stale_external_data_is_not_shown_as_current(): void
    {
        [$user, $website] = $this->portalWebsite();
        $this->external($website, ['checked_at' => now()->subHours(3), 'availability_checked_at' => now()->subHours(3), 'ssl_checked_at' => now()->subDays(3)]);
        $this->actingAs($user)->getJson("/api/portal/websites/{$website->id}")->assertJsonPath('data.availability', 'Status temporarily unavailable')->assertJsonPath('data.ssl.label', 'Status temporarily unavailable')->assertJsonPath('data.status', 'unknown');
    }

    public function test_response_time_is_available_without_a_pagespeed_score_and_backups_are_not_fabricated(): void
    {
        [$user, $website] = $this->portalWebsite();
        $this->external($website, ['response_time_ms' => 576]);
        $response = $this->actingAs($user)->getJson("/api/portal/websites/{$website->id}")->assertOk();
        $response->assertJsonPath('data.performance.label', '576ms response')->assertJsonPath('data.performance.scoring_enabled', false)->assertJsonPath('data.backups.label', 'Information unavailable');
    }

    public function test_optional_unavailable_metrics_do_not_make_a_healthy_site_critical(): void
    {
        [$user, $website] = $this->portalWebsite();
        $this->external($website);
        $this->actingAs($user)->getJson("/api/portal/websites/{$website->id}")->assertJsonPath('data.status', 'healthy')->assertJsonPath('data.backups.status', 'unknown')->assertJsonPath('data.performance.scoring_enabled', false);
    }

    public function test_admin_diagnostics_explain_stale_and_unconfigured_sources_without_secrets(): void
    {
        [, $website] = $this->portalWebsite(['agent_token_encrypted' => 'never-return-me', 'agent_token_hash' => hash('sha256', 'never-return-me')]);
        $this->external($website, ['checked_at' => now()->subHours(3), 'availability_checked_at' => now()->subHours(3)]);
        $admin = $this->user('admin');
        $response = $this->actingAs($admin)->getJson("/api/websites/{$website->id}")->assertOk()->assertJsonPath('data.data_source_diagnostics.external_monitoring.status', 'stale')->assertJsonPath('data.data_source_diagnostics.performance.status', 'not_configured');
        $this->assertStringNotContainsString('never-return-me', $response->getContent());
    }

    private function portalWebsite(array $attributes = []): array
    {
        $user = $this->user('customer');
        $customer = Customer::create(['name' => 'Customer', 'email' => $user->email, 'billing_address' => '1 Test Road', 'user_id' => $user->id]);
        $website = Website::create([...['customer_id' => $customer->id, 'name' => 'Example', 'domain' => 'example.com', 'login_url' => 'https://example.com/wp-admin', 'management_enabled' => true, 'portal_visibility' => Website::defaultPortalVisibility()], ...$attributes]);
        return [$user, $website];
    }

    private function external(Website $website, array $attributes = []): WebsiteHealthCheck
    {
        $checkedAt = $attributes['checked_at'] ?? now();
        return WebsiteHealthCheck::create([...[
            'website_id' => $website->id, 'checked_at' => $checkedAt, 'availability_checked_at' => $checkedAt,
            'ssl_checked_at' => $checkedAt, 'check_type' => 'http', 'http_status' => 200, 'response_time_ms' => 200,
            'uptime_status' => 'online', 'ssl_status' => 'valid', 'overall_status' => 'healthy',
        ], ...$attributes]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());
        return $user;
    }
}
