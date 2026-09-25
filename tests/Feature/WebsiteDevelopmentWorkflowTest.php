<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\HostingAccount;
use App\Models\HostingPackage;
use App\Models\HostingServer;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteLaunchRun;
use App\Contracts\DnsResolver;
use App\Contracts\SshCommandRunner;
use App\Contracts\SslInspector;
use App\Services\Hosting\WebsiteLaunchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebsiteDevelopmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['hosting.provisioning_mode' => 'mock', 'hosting.development_base_domain' => 'dev.web-stamp.co.uk']);
    }

    public function test_development_domain_is_generated_and_development_site_preserves_domain_history(): void
    {
        [$admin, $customer, $server, $package] = $this->fixtures();
        $domain = $this->actingAs($admin)->postJson('/api/website-provisioning/development-domain', [
            'hosting_server_id' => $server->id, 'name' => 'Acme Plumbing',
        ])->assertOk()->json('data.domain');

        $this->assertMatchesRegularExpression('/^acme-plumbing-[a-z0-9]{4}\.dev\.web-stamp\.co\.uk$/', $domain);
        $response = $this->actingAs($admin)->postJson('/api/website-provisioning', [
            'customer_id' => $customer->id, 'name' => 'Acme Plumbing', 'domain' => $domain,
            'environment' => 'development', 'hosting_server_id' => $server->id,
            'hosting_package_id' => $package->id, 'website_type' => 'wordpress',
            'options' => [], 'idempotency_key' => 'development-create-1',
        ])->assertCreated();

        $website = Website::findOrFail($response->json('data.website.id'));
        $this->assertSame('development', $website->environment);
        $this->assertSame($domain, $website->domain);
        $this->assertSame($domain, $website->current_domain);
        $this->assertSame($domain, $website->development_domain);
        $this->assertNull($website->production_domain);
        $this->assertSame('staging', $website->provisioningRuns()->firstOrFail()->options['wordpress_environment_type']);
        $this->assertTrue($website->provisioningRuns()->firstOrFail()->options['discourage_search_engines']);
    }

    public function test_launch_migration_backfills_existing_rows_as_live_without_breaking_hosting_accounts(): void
    {
        [$admin, $customer, $server] = $this->fixtures();
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'legacyusr', 'username' => 'legacyusr', 'primary_domain' => 'legacy.test', 'status' => 'active']);
        $website = Website::create(['customer_id' => $customer->id, 'hosting_server_id' => $server->id, 'hosting_account_id' => $account->id, 'name' => 'Legacy website', 'domain' => 'legacy.test', 'environment' => 'development', 'login_url' => 'https://legacy.test/wp-admin/', 'hosting_enabled' => true]);
        $migration = require database_path('migrations/2026_09_07_000000_add_development_launch_workflow.php');

        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('websites', ['id' => $website->id, 'domain' => 'legacy.test', 'environment' => 'production', 'production_domain' => 'legacy.test', 'current_domain' => 'legacy.test', 'development_domain' => null]);
        $this->assertDatabaseHas('hosting_accounts', ['id' => $account->id, 'username' => 'legacyusr', 'provider_missing' => false, 'provider_last_seen_at' => null, 'provider_missing_at' => null, 'automation_password_encrypted' => null]);
    }

    public function test_development_domain_rejects_wrong_base_and_local_collision(): void
    {
        [$admin, $customer, $server, $package] = $this->fixtures();
        Website::create(['customer_id' => $customer->id, 'name' => 'Existing', 'domain' => 'existing-ab12.dev.web-stamp.co.uk', 'current_domain' => 'existing-ab12.dev.web-stamp.co.uk', 'development_domain' => 'existing-ab12.dev.web-stamp.co.uk', 'environment' => 'development', 'login_url' => 'https://existing-ab12.dev.web-stamp.co.uk']);
        $base = ['customer_id' => $customer->id, 'name' => 'Development', 'environment' => 'development', 'hosting_server_id' => $server->id, 'hosting_package_id' => $package->id, 'website_type' => 'wordpress', 'options' => []];

        $this->actingAs($admin)->postJson('/api/website-provisioning', [...$base, 'domain' => 'wrong.example.com', 'idempotency_key' => 'wrong-base'])->assertUnprocessable()->assertJsonValidationErrors('domain');
        $this->actingAs($admin)->postJson('/api/website-provisioning', [...$base, 'domain' => 'existing-ab12.dev.web-stamp.co.uk', 'idempotency_key' => 'collision'])->assertUnprocessable()->assertJsonValidationErrors('domain');
    }

    public function test_successful_sync_marks_absent_accounts_missing_and_excludes_them_from_import(): void
    {
        [$admin, $customer, $server] = $this->fixtures();
        $stale = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'deletedusr', 'username' => 'deletedusr', 'primary_domain' => 'deleted.test', 'status' => 'active', 'last_synced_at' => now()]);
        $linked = Website::create(['customer_id' => $customer->id, 'hosting_server_id' => $server->id, 'hosting_account_id' => $stale->id, 'name' => 'Historical website', 'domain' => 'deleted.test', 'current_domain' => 'deleted.test', 'production_domain' => 'deleted.test', 'environment' => 'production', 'login_url' => 'https://deleted.test', 'hosting_enabled' => true]);
        $server->update(['metadata' => ['mock_accounts' => [['external_id' => 'liveusr', 'username' => 'liveusr', 'primary_domain' => 'live.test', 'status' => 'active']]]]);

        $this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/sync")->assertOk()->assertJsonPath('data.missing', 1);
        $this->assertTrue($stale->fresh()->provider_missing);
        $scan = $this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/discover-websites")->assertOk();
        $this->assertNotContains('deleted.test', collect($scan->json('data.domains'))->pluck('domain')->all());
        $this->actingAs($admin)->getJson("/api/websites/{$linked->id}")->assertOk()->assertJsonPath('data.hosting_account.provider_missing', true)->assertJsonPath('data.hosting_connected', false);
        $this->actingAs($admin)->putJson("/api/hosting-accounts/{$stale->id}", ['customer_id' => $customer->id])->assertUnprocessable();
    }

    public function test_failed_whm_scan_never_marks_existing_accounts_missing(): void
    {
        $admin = $this->user('admin');
        $server = HostingServer::create(['name' => 'Krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'keepme', 'username' => 'keepme', 'primary_domain' => 'keep.test', 'status' => 'active', 'last_synced_at' => now()]);
        Http::fake(['*' => Http::response(['metadata' => ['result' => 0, 'reason' => 'Temporary WHM failure']], 500)]);

        $this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/sync")->assertUnprocessable();
        $this->assertFalse($account->fresh()->provider_missing);
        $this->assertNull($account->fresh()->provider_missing_at);
    }

    public function test_go_live_is_admin_only_and_requires_development_hosting_credentials(): void
    {
        [$admin, $customer, $server] = $this->fixtures();
        $staff = $this->user('staff');
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'site-ab12.dev.web-stamp.co.uk', 'status' => 'active', 'last_synced_at' => now()]);
        $website = Website::create(['customer_id' => $customer->id, 'hosting_server_id' => $server->id, 'hosting_account_id' => $account->id, 'name' => 'Development site', 'domain' => $account->primary_domain, 'current_domain' => $account->primary_domain, 'development_domain' => $account->primary_domain, 'environment' => 'development', 'login_url' => 'https://'.$account->primary_domain.'/wp-admin/', 'hosting_enabled' => true, 'wordpress_enabled' => true]);

        $this->actingAs($staff)->postJson("/api/websites/{$website->id}/go-live", ['production_domain' => 'site.test', 'idempotency_key' => 'launch-1'])->assertForbidden();
        $this->actingAs($admin)->postJson("/api/websites/{$website->id}/go-live", ['production_domain' => 'site.test', 'idempotency_key' => 'launch-1'])->assertUnprocessable()->assertJsonValidationErrors('hosting');
    }

    public function test_go_live_attaches_production_domain_without_changing_primary_domain(): void
    {
        $admin = $this->user('admin');
        $customer = Customer::create(['name' => 'Client', 'email' => fake()->unique()->safeEmail(), 'billing_address' => '1 Test Road']);
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'site-ab12.dev.web-stamp.co.uk', 'assigned_ip' => '192.0.2.10', 'status' => 'active', 'last_synced_at' => now(), 'automation_password_encrypted' => 'cpanel-secret']);
        $website = Website::create(['customer_id' => $customer->id, 'hosting_server_id' => $server->id, 'hosting_account_id' => $account->id, 'name' => 'Development site', 'domain' => $account->primary_domain, 'current_domain' => $account->primary_domain, 'development_domain' => $account->primary_domain, 'environment' => 'development', 'login_url' => 'https://'.$account->primary_domain.'/wp-admin/', 'hosting_enabled' => true, 'wordpress_enabled' => true, 'monitoring_enabled' => false]);
        $run = WebsiteLaunchRun::create(['public_id' => (string) Str::uuid(), 'website_id' => $website->id, 'hosting_account_id' => $account->id, 'initiated_by_user_id' => $admin->id, 'idempotency_key' => 'launch-success', 'development_domain' => $account->primary_domain, 'production_domain' => 'site.test', 'options' => ['enable_indexing' => true]]);
        foreach (['preflight', 'check_dns', 'change_primary_domain', 'reconcile_account', 'migrate_wordpress', 'trigger_autossl', 'check_ssl', 'verify_production'] as $step) $run->steps()->create(['step' => $step]);
        $run->steps()->where('step', 'change_primary_domain')->update(['status' => 'failed', 'safe_message' => 'modifyacct permission denied']);
        $run->update(['state' => 'failed', 'failed_step' => 'change_primary_domain', 'safe_error' => 'modifyacct permission denied']);

        $addon = false;
        Http::fake(function ($request) use (&$addon) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'site-ab12.dev.web-stamp.co.uk', 'ip' => '192.0.2.10', 'plan' => 'Standard', 'suspended' => 0]]]]);
            if (str_contains($request->url(), '/cpanel') && (int) $request['cpanel_jsonapi_apiversion'] === 2 && $request['cpanel_jsonapi_func'] === 'addaddondomain') { $addon = true; return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]); }
            if (str_contains($request->url(), '/cpanel') && (int) $request['cpanel_jsonapi_apiversion'] === 2) return Http::response(['cpanelresult' => ['event' => ['result' => 1], 'data' => $addon ? [['domain' => 'site.test', 'dir' => '/public_html']] : []]]);
            if (str_contains($request->url(), '/cpanel')) return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'site-ab12.dev.web-stamp.co.uk', 'addon_domains' => $addon ? ['site.test'] : []]]]]);
            if (str_contains($request->url(), '/uapi_cpanel')) return Http::response(['metadata' => ['result' => 1], 'data' => ['uapi' => ['status' => 1, 'errors' => null, 'messages' => null, 'data' => null]]]);
            return Http::response(str_repeat('<p>Website content.</p>', 20), 200);
        });
        $dns = new class implements DnsResolver {
            public bool $ready = false;
            public function aRecords(string $domain): array { return $this->ready ? ['192.0.2.10'] : []; }
            public function cnameRecords(string $domain): array { return []; }
            public function nameservers(string $domain): array { return ['ns1.krystal.uk']; }
        };
        $this->app->instance(DnsResolver::class, $dns);
        $this->app->instance(SslInspector::class, new class implements SslInspector {
            public function inspect(string $domain): array { return ['valid' => true, 'hostname_match' => true, 'status' => 'active', 'issuer' => 'Test CA', 'expires_at' => now()->addMonth()->toIso8601String()]; }
        });
        $this->app->instance(SshCommandRunner::class, new class implements SshCommandRunner {
            private bool $migrated = false;
            public function run(HostingServer $server, HostingAccount $account, string $password, string $command, int $timeout = 60): array {
                if (str_contains($command, "__WEBSTAMP_CONNECTED__")) $output = '__WEBSTAMP_CONNECTED__';
                elseif (str_contains($command, '__WEBSTAMP_TOOLS_READY__')) $output = '__WEBSTAMP_TOOLS_READY__';
                elseif (str_contains($command, '__WEBSTAMP_HTACCESS__')) $output = '__WEBSTAMP_HTACCESS__'.base64_encode("# php -- BEGIN cPanel-generated handler\nAddHandler application/x-httpd-ea-php83 .php\n# php -- END cPanel-generated handler\n");
                elseif (str_contains($command, '__WEBSTAMP_HTACCESS_UPDATED__')) $output = '__WEBSTAMP_HTACCESS_UPDATED__';
                elseif (str_contains($command, '__WEBSTAMP_HTACCESS_VERIFIED__')) $output = '__WEBSTAMP_HTACCESS_VERIFIED__';
                elseif (str_contains($command, '__WEBSTAMP_REDIRECT_READY__')) $output = '__WEBSTAMP_REDIRECT_READY__';
                elseif (str_contains($command, "'search-replace'") && ! str_contains($command, '--dry-run')) { $this->migrated = true; $output = 'Success'; }
                elseif (str_contains($command, "'option' 'get' 'siteurl'") || str_contains($command, "'option' 'get' 'home'")) $output = $this->migrated ? 'https://site.test' : 'https://site-ab12.dev.web-stamp.co.uk';
                elseif (str_contains($command, "'db' 'tables'")) $output = 'wp_options';
                else $output = 'Success';
                return ['exit_code' => 0, 'stdout' => $output, 'stderr' => ''];
            }
        });

        $result = app(WebsiteLaunchService::class)->process($run);
        $this->assertSame('waiting_for_dns', $result->state, (string) $result->safe_error);
        $this->assertTrue($addon);
        $this->assertSame('site-ab12.dev.web-stamp.co.uk', $website->fresh()->current_domain);
        $this->assertSame('site-ab12.dev.web-stamp.co.uk', $account->fresh()->primary_domain);
        $dns->ready = true;
        $result = app(WebsiteLaunchService::class)->process($run->fresh());
        $this->assertSame('complete', $result->state);
        $this->assertSame('production', $website->fresh()->environment);
        $this->assertSame('site.test', $website->fresh()->current_domain);
        $this->assertSame('site-ab12.dev.web-stamp.co.uk', $website->fresh()->development_domain);
        $this->assertSame('site-ab12.dev.web-stamp.co.uk', $account->fresh()->primary_domain);
        $this->assertContains(['domain' => 'site.test', 'type' => 'addon'], $account->fresh()->domains);
        $this->assertNotNull($website->fresh()->went_live_at);
        $this->assertSame(1, $run->steps()->where('step', 'attach_production_domain')->value('attempts'));
        $this->assertFalse($run->steps()->where('step', 'change_primary_domain')->exists());
        $this->assertFalse($run->steps()->where('step', 'reconcile_account')->exists());
        $this->assertTrue($run->steps()->where('step', 'redirect_development_domain')->exists());
        $legacyMetadata = $run->steps()->where('step', 'attach_production_domain')->firstOrFail()->metadata;
        $this->assertSame('change_primary_domain', $legacyMetadata['legacy_step']);
        $this->assertSame('modifyacct permission denied', $legacyMetadata['legacy_safe_message']);
        $this->assertSame(2, $run->steps()->where('step', 'check_dns')->value('attempts'));
        app(WebsiteLaunchService::class)->process($run->fresh());
        $this->assertSame(1, $run->steps()->where('step', 'attach_production_domain')->value('attempts'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/modifyacct'));
    }

    public function test_check_dns_waits_when_the_production_domain_is_not_actually_serving_the_site_yet(): void
    {
        // Reproduces the real incident: cPanel reports the addon domain as
        // successfully attached (DNS is correct, listaddondomains says
        // public_html), but the underlying vhost never actually finished
        // deploying, so the domain doesn't serve anything yet. The pipeline
        // must catch this itself and wait/retry rather than sailing on to
        // request a certificate for a domain that isn't really live.
        $admin = $this->user('admin');
        $customer = Customer::create(['name' => 'Client', 'email' => fake()->unique()->safeEmail(), 'billing_address' => '1 Test Road']);
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'site-ab12.dev.web-stamp.co.uk', 'assigned_ip' => '192.0.2.10', 'status' => 'active', 'last_synced_at' => now(), 'automation_password_encrypted' => 'cpanel-secret']);
        $website = Website::create(['customer_id' => $customer->id, 'hosting_server_id' => $server->id, 'hosting_account_id' => $account->id, 'name' => 'Development site', 'domain' => $account->primary_domain, 'current_domain' => $account->primary_domain, 'development_domain' => $account->primary_domain, 'environment' => 'development', 'login_url' => 'https://'.$account->primary_domain.'/wp-admin/', 'hosting_enabled' => true, 'wordpress_enabled' => true, 'monitoring_enabled' => false]);
        $run = WebsiteLaunchRun::create(['public_id' => (string) Str::uuid(), 'website_id' => $website->id, 'hosting_account_id' => $account->id, 'initiated_by_user_id' => $admin->id, 'idempotency_key' => 'launch-broken-vhost', 'development_domain' => $account->primary_domain, 'production_domain' => 'site.test', 'expected_ip' => '192.0.2.10', 'options' => ['enable_indexing' => true]]);
        foreach (['preflight', 'attach_production_domain', 'verify_production_domain', 'check_dns', 'trigger_autossl', 'check_ssl', 'migrate_wordpress', 'verify_production', 'redirect_development_domain'] as $step) $run->steps()->create(['step' => $step]);
        $run->steps()->whereIn('step', ['preflight', 'attach_production_domain', 'verify_production_domain'])->update(['status' => 'complete']);
        $run->update(['state' => 'checking_dns']);

        $autosslTriggered = false;
        Http::fake(function ($request) use (&$autosslTriggered) {
            $url = $request->url();
            if (str_contains($url, '/cpanel') && (int) $request['cpanel_jsonapi_apiversion'] === 2) return Http::response(['cpanelresult' => ['event' => ['result' => 1], 'data' => [['domain' => 'site.test', 'dir' => '/public_html']]]]);
            if (str_contains($url, '/cpanel') && $request['cpanel_jsonapi_func'] === 'start_autossl_check') { $autosslTriggered = true; return Http::response(['metadata' => ['result' => 1]]); }
            // The domain resolves and cPanel believes it's attached, but nothing is actually served yet.
            if (str_starts_with($url, 'http://site.test')) return Http::response('', 200);
            return Http::response('ok', 200);
        });
        $this->app->instance(DnsResolver::class, new class implements DnsResolver {
            public function aRecords(string $domain): array { return ['192.0.2.10']; }
            public function cnameRecords(string $domain): array { return []; }
            public function nameservers(string $domain): array { return ['ns1.krystal.uk']; }
        });

        $result = app(WebsiteLaunchService::class)->process($run->fresh());

        $this->assertSame('waiting_for_dns', $result->state);
        $this->assertFalse($autosslTriggered);
        $step = $result->steps()->where('step', 'check_dns')->firstOrFail();
        $this->assertSame('waiting', $step->status);
        $this->assertStringContainsString('not yet serving the website', $step->safe_message);
    }

    public function test_go_live_request_creates_a_resumable_run_without_changing_the_current_domain(): void
    {
        $admin = $this->user('admin');
        $customer = Customer::create(['name' => 'Client', 'email' => fake()->unique()->safeEmail(), 'billing_address' => '1 Test Road']);
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'site-ab12.dev.web-stamp.co.uk', 'assigned_ip' => '192.0.2.10', 'status' => 'active', 'last_synced_at' => now(), 'automation_password_encrypted' => 'cpanel-secret']);
        $website = Website::create(['customer_id' => $customer->id, 'hosting_server_id' => $server->id, 'hosting_account_id' => $account->id, 'name' => 'Development site', 'domain' => $account->primary_domain, 'current_domain' => $account->primary_domain, 'development_domain' => $account->primary_domain, 'environment' => 'development', 'login_url' => 'https://'.$account->primary_domain.'/wp-admin/', 'hosting_enabled' => true, 'wordpress_enabled' => true]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'site-ab12.dev.web-stamp.co.uk', 'ip' => '192.0.2.10', 'suspended' => 0]]]]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'site-ab12.dev.web-stamp.co.uk', 'addon_domains' => []]]]]);
        });
        Queue::fake();

        $response = $this->actingAs($admin)->postJson("/api/websites/{$website->id}/go-live", ['production_domain' => 'site.test', 'enable_indexing' => false, 'idempotency_key' => 'launch-controller'])->assertCreated();
        $this->assertSame('site.test', $response->json('data.production_domain'));
        $this->assertSame('site-ab12.dev.web-stamp.co.uk', $website->fresh()->current_domain);
        $this->assertSame('site.test', $website->fresh()->production_domain);
        $this->assertDatabaseCount('website_launch_steps', 9);
        Queue::assertPushed(\App\Jobs\ProcessWebsiteLaunch::class);
    }

    public function test_go_live_preflight_checks_the_entered_domain_before_starting(): void
    {
        $admin = $this->user('admin');
        $customer = Customer::create(['name' => 'Client', 'email' => fake()->unique()->safeEmail(), 'billing_address' => '1 Test Road']);
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'site-ab12.dev.web-stamp.co.uk', 'status' => 'active', 'last_synced_at' => now(), 'automation_password_encrypted' => 'cpanel-secret']);
        $website = Website::create(['customer_id' => $customer->id, 'hosting_server_id' => $server->id, 'hosting_account_id' => $account->id, 'name' => 'Development site', 'domain' => $account->primary_domain, 'current_domain' => $account->primary_domain, 'development_domain' => $account->primary_domain, 'environment' => 'development', 'login_url' => 'https://'.$account->primary_domain.'/wp-admin/', 'hosting_enabled' => true, 'wordpress_enabled' => true]);
        Website::create(['customer_id' => $customer->id, 'name' => 'Existing live site', 'domain' => 'taken.test', 'current_domain' => 'taken.test', 'production_domain' => 'taken.test', 'environment' => 'production', 'login_url' => 'https://taken.test']);

        Http::fake(['*' => Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => []]])]);

        $this->actingAs($admin)->getJson("/api/websites/{$website->id}/go-live/preflight?production_domain=taken.test")
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonFragment(['This domain is already used by another CRM website.']);
    }

    public function test_disposable_development_site_completes_the_full_go_live_lifecycle_safely(): void
    {
        config(['hosting.provisioning_mode' => 'live', 'hosting.allow_live_provisioning' => true]);
        $admin = $this->user('admin');
        $customer = Customer::create(['name' => 'Disposable Client', 'email' => fake()->unique()->safeEmail(), 'billing_address' => '1 Test Road']);
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret'], 'metadata' => ['development_base_domain' => 'dev.web-stamp.co.uk', 'ssh_host_fingerprint' => str_repeat('a', 64)]]);
        $package = HostingPackage::create(['hosting_server_id' => $server->id, 'external_id' => 'Standard', 'name' => 'Standard', 'shell_access' => true]);
        $productionDomain = 'disposable-launch.test';
        $whm = (object) ['created' => false, 'primary_domain' => null, 'addon_domains' => [], 'username' => 'disposable1', 'modify_calls' => 0, 'addon_calls' => 0, 'addon_parameters' => [], 'fail' => false, 'create_parameters' => []];

        Http::fake(function ($request) use ($whm) {
            $url = $request->url();
            if ($whm->fail && str_contains($url, '/listaccts')) return Http::response(['metadata' => ['result' => 0, 'reason' => 'Temporary WHM failure']], 200);
            if (str_contains($url, '/listpkgs')) return Http::response(['metadata' => ['result' => 1], 'data' => ['pkg' => [['name' => 'Standard', 'HASSHELL' => 1]]]]);
            if (str_contains($url, '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => $whm->created ? [['user' => $whm->username, 'domain' => $whm->primary_domain, 'ip' => '192.0.2.44', 'plan' => 'Standard', 'suspended' => 0, 'owner' => 'reseller']] : []]]);
            if (str_contains($url, '/createacct')) { $whm->created = true; $whm->primary_domain = strtolower((string) $request['domain']); $whm->create_parameters = $request->data(); return Http::response(['metadata' => ['result' => 1, 'reason' => 'Account created'], 'data' => ['ip' => '192.0.2.44']]); }
            if (str_contains($url, '/modifyacct')) { $whm->modify_calls++; return Http::response(['metadata' => ['result' => 0, 'reason' => 'Forbidden']]); }
            if (str_contains($url, '/accountsummary')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => $whm->username, 'shell' => '/bin/bash']]]]);
            if (str_contains($url, '/cpanel') && (int) $request['cpanel_jsonapi_apiversion'] === 2 && $request['cpanel_jsonapi_func'] === 'addaddondomain') { $whm->addon_calls++; $whm->addon_parameters = $request->data(); $whm->addon_domains[] = strtolower((string) $request['newdomain']); return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]); }
            if (str_contains($url, '/cpanel') && (int) $request['cpanel_jsonapi_apiversion'] === 2) { $data = collect($whm->addon_domains)->map(fn ($domain) => ['domain' => $domain, 'basedir' => 'public_html', 'reldir' => 'home:public_html', 'dir' => '/home/'.$whm->username.'/public_html'])->all(); return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => $data]]]); }
            if (str_contains($url, '/cpanel')) return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => $whm->primary_domain, 'addon_domains' => $whm->addon_domains]]]]);
            if (str_contains($url, '/uapi_cpanel')) { $data = $request['cpanel.function'] === 'get_restrictions' ? ['prefix' => $whm->username.'_', 'max_database_name_length' => 64, 'max_username_length' => 32] : null; return Http::response(['metadata' => ['result' => 1, 'reason' => 'OK'], 'data' => ['uapi' => ['status' => 1, 'errors' => null, 'messages' => null, 'data' => $data]]]); }
            if (str_starts_with($url, 'http://')) return Http::response('', 301, ['Location' => preg_replace('/^http:/', 'https:', $url)]);
            if (str_starts_with($url, 'https://')) return Http::response(str_repeat('<p>Website content.</p>', 20), 200);
            return Http::response([], 404);
        });

        $dns = new class implements DnsResolver {
            public string $developmentDomain = '';
            public string $productionDomain = '';
            public bool $productionReady = false;
            public function aRecords(string $domain): array { return $domain === $this->developmentDomain || ($this->productionReady && in_array($domain, [$this->productionDomain, 'www.'.$this->productionDomain], true)) ? ['192.0.2.44'] : []; }
            public function cnameRecords(string $domain): array { return []; }
            public function nameservers(string $domain): array { return ['ns1.krystal.uk']; }
        };
        $ssl = new class implements SslInspector {
            public string $productionDomain = '';
            public bool $productionReady = false;
            public function inspect(string $domain): array { $valid = $domain !== $this->productionDomain && $domain !== 'www.'.$this->productionDomain ? true : $this->productionReady; return ['valid' => $valid, 'hostname_match' => $valid, 'status' => $valid ? 'active' : 'pending', 'issuer' => $valid ? 'Test CA' : null, 'expires_at' => $valid ? now()->addMonth()->toIso8601String() : null]; }
        };
        $ssh = new class implements SshCommandRunner {
            public string $url = '';
            public array $commands = [];
            public bool $wpConfig = false;
            public bool $wordpress = false;
            public bool $indexingDisabled = false;
            public bool $stagingEnvironment = false;
            public bool $productionEnvironment = false;
            public string $redirectBlock = '';
            public function run(HostingServer $server, HostingAccount $account, string $password, string $command, int $timeout = 60): array {
                $this->commands[] = $command;
                if (str_contains($command, '__WEBSTAMP_CONNECTED__')) $output = '__WEBSTAMP_CONNECTED__';
                elseif (str_contains($command, '__WEBSTAMP_TOOLS_READY__')) $output = '__WEBSTAMP_TOOLS_READY__';
                elseif (str_contains($command, '__WEBSTAMP_HTACCESS__')) $output = '__WEBSTAMP_HTACCESS__'.base64_encode("# php -- BEGIN cPanel-generated handler\nAddHandler application/x-httpd-ea-php83 .php\n# php -- END cPanel-generated handler\n");
                elseif (str_contains($command, '__WEBSTAMP_HTACCESS_UPDATED__')) $output = '__WEBSTAMP_HTACCESS_UPDATED__';
                elseif (str_contains($command, '__WEBSTAMP_HTACCESS_VERIFIED__')) $output = '__WEBSTAMP_HTACCESS_VERIFIED__';
                elseif (str_contains($command, '__WEBSTAMP_REDIRECT_READY__')) { preg_match('/base64_decode\("([A-Za-z0-9+\/=]+)"/', $command, $match); $this->redirectBlock = base64_decode($match[1] ?? '', true) ?: ''; $output = '__WEBSTAMP_REDIRECT_READY__'; }
                elseif ($command === 'test -f public_html/wp-load.php') return ['exit_code' => $this->wordpress ? 0 : 1, 'stdout' => '', 'stderr' => ''];
                elseif ($command === 'test -f public_html/wp-config.php') return ['exit_code' => $this->wpConfig ? 0 : 1, 'stdout' => '', 'stderr' => ''];
                elseif (str_contains($command, "'core' 'is-installed'")) return ['exit_code' => $this->wordpress ? 0 : 1, 'stdout' => '', 'stderr' => ''];
                elseif (str_contains($command, "'core' 'download'")) { $this->wordpress = true; $output = 'Downloaded'; }
                elseif (str_contains($command, "'config' 'create'")) { $this->wpConfig = true; $output = 'Created'; }
                elseif (str_contains($command, "'core' 'install'")) { preg_match("/--url=([^']+)/", $command, $match); $this->url = $match[1] ?? $this->url; $this->wordpress = true; $output = 'Installed'; }
                elseif (str_contains($command, "'option' 'get' 'siteurl'") || str_contains($command, "'option' 'get' 'home'")) $output = $this->url;
                elseif (str_contains($command, "'option' 'update' 'siteurl'") || str_contains($command, "'option' 'update' 'home'")) { preg_match("~'https://[^']+'~", $command, $match); $this->url = trim($match[0] ?? $this->url, "'"); $output = 'Updated'; }
                elseif (str_contains($command, "'option' 'update' 'blog_public' '0'")) { $this->indexingDisabled = true; $output = 'Updated'; }
                elseif (str_contains($command, "'config' 'set' 'WP_ENVIRONMENT_TYPE' 'staging'")) { $this->stagingEnvironment = true; $output = 'Updated'; }
                elseif (str_contains($command, "'config' 'set' 'WP_ENVIRONMENT_TYPE' 'production'")) { $this->productionEnvironment = true; $output = 'Updated'; }
                elseif (str_contains($command, "'db' 'tables'")) $output = 'wp_options';
                else $output = '';
                return ['exit_code' => 0, 'stdout' => $output, 'stderr' => ''];
            }
        };
        $this->app->instance(DnsResolver::class, $dns);
        $this->app->instance(SslInspector::class, $ssl);
        $this->app->instance(SshCommandRunner::class, $ssh);

        $developmentDomain = $this->actingAs($admin)->postJson('/api/website-provisioning/development-domain', ['hosting_server_id' => $server->id, 'name' => 'Disposable Website'])->assertOk()->json('data.domain');
        $this->assertMatchesRegularExpression('/^disposable-website-[a-z0-9]{4}\.dev\.web-stamp\.co\.uk$/', $developmentDomain);
        $this->assertDatabaseMissing('websites', ['domain' => $developmentDomain]);
        $dns->developmentDomain = $developmentDomain;
        $dns->productionDomain = $productionDomain;
        $ssl->productionDomain = $productionDomain;

        $created = $this->actingAs($admin)->postJson('/api/website-provisioning', [
            'customer_id' => $customer->id, 'name' => 'Disposable Website', 'domain' => $developmentDomain,
            'environment' => 'development', 'hosting_server_id' => $server->id, 'hosting_package_id' => $package->id,
            'website_type' => 'wordpress', 'options' => [], 'idempotency_key' => 'disposable-development-lifecycle',
        ])->assertCreated();
        $website = Website::findOrFail($created->json('data.website.id'));
        $provisioningRun = $website->provisioningRuns()->firstOrFail();
        $this->assertSame('complete', $provisioningRun->state);
        $this->assertSame('development', $website->fresh()->environment);
        $this->assertSame('staging', $provisioningRun->options['wordpress_environment_type']);
        $this->assertTrue($provisioningRun->options['discourage_search_engines']);
        $this->assertTrue($ssh->stagingEnvironment);
        $this->assertTrue($ssh->indexingDisabled);
        $this->assertSame('Standard', $whm->create_parameters['plan']);
        $secondGeneratedDomain = $this->actingAs($admin)->postJson('/api/website-provisioning/development-domain', ['hosting_server_id' => $server->id, 'name' => 'Disposable Website'])->assertOk()->json('data.domain');
        $this->assertNotSame($developmentDomain, $secondGeneratedDomain);
        $website->update(['monitoring_enabled' => false]);

        Website::create(['customer_id' => $customer->id, 'name' => 'Conflict', 'domain' => 'conflict.test', 'current_domain' => 'conflict.test', 'production_domain' => 'conflict.test', 'environment' => 'production', 'login_url' => 'https://conflict.test']);
        $this->actingAs($admin)->getJson("/api/websites/{$website->id}/go-live/preflight?production_domain=conflict.test")->assertOk()->assertJsonPath('data.ready', false);
        $this->actingAs($admin)->getJson("/api/websites/{$website->id}/go-live/preflight?production_domain={$productionDomain}")->assertOk()->assertJsonPath('data.ready', true);

        $launchResponse = $this->actingAs($admin)->postJson("/api/websites/{$website->id}/go-live", ['production_domain' => $productionDomain, 'enable_indexing' => true, 'idempotency_key' => 'disposable-launch-lifecycle'])->assertCreated();
        $launch = WebsiteLaunchRun::findOrFail($launchResponse->json('data.id'));
        $this->assertSame('waiting_for_dns', $launch->fresh()->state);
        $this->assertSame(0, $whm->modify_calls);
        $this->assertSame(1, $whm->addon_calls);
        $this->assertSame($productionDomain, $whm->addon_parameters['newdomain']);
        $this->assertSame('public_html', $whm->addon_parameters['dir']);
        $this->assertSame($whm->username, $whm->addon_parameters['cpanel_jsonapi_user']);
        $this->assertSame($developmentDomain, $whm->primary_domain);

        $dns->productionReady = true;
        app(WebsiteLaunchService::class)->process($launch->fresh());
        $this->assertSame('waiting_for_ssl', $launch->fresh()->state);
        $this->assertSame(0, $whm->modify_calls);
        $this->assertSame(1, $whm->addon_calls);
        $this->assertSame($developmentDomain, $whm->primary_domain);
        $this->assertSame($whm->username, $website->fresh()->cpanel_username);
        $this->assertFalse(collect($ssh->commands)->contains(fn ($command) => str_contains($command, "'search-replace'")));

        $ssl->productionReady = true;
        app(WebsiteLaunchService::class)->process($launch->fresh());
        $website->refresh();
        $this->assertSame('complete', $launch->fresh()->state, (string) $launch->fresh()->safe_error);
        $this->assertSame('production', $website->environment);
        $this->assertNotNull($website->went_live_at);
        $this->assertSame($developmentDomain, $website->development_domain);
        $this->assertSame($productionDomain, $website->current_domain);
        $this->assertSame($developmentDomain, $website->hostingAccount->primary_domain);
        $this->assertTrue(collect($ssh->commands)->contains(fn ($command) => str_contains($command, "'search-replace'") && str_contains($command, '--dry-run')));
        $this->assertTrue(collect($ssh->commands)->contains(fn ($command) => str_contains($command, "'search-replace'") && ! str_contains($command, '--dry-run')));
        $this->assertTrue(collect($ssh->commands)->contains(fn ($command) => str_contains($command, "'option' 'update' 'siteurl' 'https://{$productionDomain}'")));
        $this->assertTrue(collect($ssh->commands)->contains(fn ($command) => str_contains($command, "'option' 'update' 'home' 'https://{$productionDomain}'")));
        $this->assertTrue(collect($ssh->commands)->contains(fn ($command) => str_contains($command, '__WEBSTAMP_REDIRECT_READY__')));
        $this->assertStringContainsString('RewriteCond %{HTTP_HOST} ^'.preg_quote($developmentDomain, '/').'$ [NC]', $ssh->redirectBlock);
        $this->assertStringContainsString('RewriteRule ^ https://'.$productionDomain.'%{REQUEST_URI} [R=301,L,NE]', $ssh->redirectBlock);
        $this->assertSame('https://'.$productionDomain, $ssh->url);
        $this->assertTrue($ssh->productionEnvironment);
        $modifyCalls = $whm->modify_calls;
        $addonCalls = $whm->addon_calls;
        $searchReplaceCalls = collect($ssh->commands)->filter(fn ($command) => str_contains($command, "'search-replace'"))->count();
        app(WebsiteLaunchService::class)->process($launch->fresh());
        $this->assertSame($modifyCalls, $whm->modify_calls);
        $this->assertSame($addonCalls, $whm->addon_calls);
        $this->assertSame($searchReplaceCalls, collect($ssh->commands)->filter(fn ($command) => str_contains($command, "'search-replace'"))->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/modifyacct'));

        $stale = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'terminated1', 'username' => 'terminated1', 'primary_domain' => 'terminated.test', 'status' => 'active', 'last_synced_at' => now()]);
        $this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/sync")->assertOk();
        $this->assertTrue($stale->fresh()->provider_missing);
        $this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/discover-websites")->assertOk()->assertJsonMissing(['domain' => 'terminated.test']);
        $current = $website->hostingAccount()->firstOrFail();
        $whm->fail = true;
        $this->actingAs($admin)->postJson("/api/hosting-servers/{$server->id}/sync")->assertUnprocessable();
        $this->assertFalse($current->fresh()->provider_missing);
    }

    public function test_verify_production_restores_and_authenticates_monitoring_on_the_production_domain(): void
    {
        [$run, $website] = $this->monitoringLaunchFixture();
        $ssh = new LaunchMonitoringSshRunner('https://copperingots.uk');
        $this->app->instance(SshCommandRunner::class, $ssh);
        $this->app->instance(SslInspector::class, new FakeLaunchSslInspector);
        config(['website-audits.enforce_public_networks' => false]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://copperingots.uk/wp-json/webstamp/v1/status') {
                $this->assertSame('Bearer existing-agent-token', $request->header('Authorization')[0] ?? null);
                return Http::response(['wordpress_version' => '6.9', 'plugin_count' => 4, 'plugin_updates' => 0]);
            }
            if (str_starts_with($request->url(), 'http://')) return Http::response('', 301, ['Location' => preg_replace('/^http:/', 'https:', $request->url())]);
            return Http::response('', 200);
        });

        $result = app(WebsiteLaunchService::class)->process($run);

        $this->assertSame('complete', $result->state, (string) $result->safe_error);
        $website->refresh();
        $this->assertSame(hash('sha256', 'existing-agent-token'), $website->agent_token_hash);
        $this->assertSame('existing-agent-token', $website->agent_token_encrypted);
        $this->assertNotNull($website->agent_last_seen_at);
        $this->assertSame('copperingots.uk', $website->current_domain);
        $this->assertSame('test2-wzaz.sites.web-stamp.co.uk', $website->development_domain);
        $this->assertTrue(collect($ssh->commands)->contains(fn ($command) => str_contains($command, "'option' 'update' 'webstamp_agent_token' 'existing-agent-token'")));
        Http::assertSent(fn ($request) => $request->url() === 'https://copperingots.uk/wp-json/webstamp/v1/status');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'test2-wzaz.sites.web-stamp.co.uk/wp-json'));

        $commandCount = count($ssh->commands);
        $verifyAttempts = $run->steps()->where('step', 'verify_production')->value('attempts');
        app(WebsiteLaunchService::class)->process($run->fresh());
        $this->assertSame($commandCount, count($ssh->commands));
        $this->assertSame($verifyAttempts, $run->steps()->where('step', 'verify_production')->value('attempts'));
        $this->assertSame(1, $run->steps()->where('step', 'attach_production_domain')->value('attempts'));
    }

    public function test_wrong_monitoring_identity_is_rejected_without_exposing_secrets(): void
    {
        [$run] = $this->monitoringLaunchFixture(['agent_token_hash' => hash('sha256', 'different-website-token')]);
        $ssh = new LaunchMonitoringSshRunner('https://copperingots.uk');
        $this->app->instance(SshCommandRunner::class, $ssh);
        $this->app->instance(SslInspector::class, new FakeLaunchSslInspector);
        config(['website-audits.enforce_public_networks' => false]);
        Http::fake(function ($request) {
            if (str_starts_with($request->url(), 'http://')) return Http::response('', 301, ['Location' => preg_replace('/^http:/', 'https:', $request->url())]);
            return Http::response('', 200);
        });

        $result = app(WebsiteLaunchService::class)->process($run);

        $this->assertSame('failed', $result->state);
        $this->assertSame('verify_production', $result->failed_step);
        $this->assertStringContainsString('monitoring identity is unavailable or invalid', $result->safe_error);
        $this->assertStringNotContainsString('existing-agent-token', $result->safe_error);
        $this->assertStringNotContainsString('different-website-token', $result->safe_error);
        $this->assertFalse(collect($ssh->commands)->contains(fn ($command) => str_contains($command, 'webstamp_agent_token')));
    }

    public function test_stale_development_monitoring_cannot_complete_launch_when_production_endpoint_rejects_identity(): void
    {
        [$run, $website] = $this->monitoringLaunchFixture(['agent_last_seen_at' => now()]);
        $ssh = new LaunchMonitoringSshRunner('https://copperingots.uk');
        $this->app->instance(SshCommandRunner::class, $ssh);
        $this->app->instance(SslInspector::class, new FakeLaunchSslInspector);
        config(['website-audits.enforce_public_networks' => false]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://copperingots.uk/wp-json/webstamp/v1/status') return Http::response(['message' => 'Unauthorized'], 401);
            if ($request->url() === 'https://test2-wzaz.sites.web-stamp.co.uk/wp-json/webstamp/v1/status') return Http::response(['wordpress_version' => '6.9']);
            if (str_starts_with($request->url(), 'http://')) return Http::response('', 301, ['Location' => preg_replace('/^http:/', 'https:', $request->url())]);
            return Http::response('', 200);
        });

        $result = app(WebsiteLaunchService::class)->process($run);

        $this->assertSame('failed', $result->state);
        $this->assertSame('verify_production', $result->failed_step);
        $this->assertSame('The website is live, but the monitoring plugin has not reconnected on the production domain yet.', $result->safe_error);
        $this->assertNotNull($website->fresh()->agent_last_failed_at);
        $this->assertStringNotContainsString('existing-agent-token', $result->safe_error);
        $this->assertSame(1, $run->steps()->where('step', 'attach_production_domain')->value('attempts'));
        Http::assertSent(fn ($request) => $request->url() === 'https://copperingots.uk/wp-json/webstamp/v1/status');
        Http::assertNotSent(fn ($request) => $request->url() === 'https://test2-wzaz.sites.web-stamp.co.uk/wp-json/webstamp/v1/status');
    }

    public function test_addon_domain_conflict_on_another_account_is_rejected_without_changes(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [
                ['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0],
                ['user' => 'otherusr', 'domain' => 'other.example.test', 'suspended' => 0],
            ]]]);
            if (str_contains($request->url(), '/cpanel') && $request['cpanel_jsonapi_user'] === 'otherusr') return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'other.example.test', 'addon_domains' => ['live.example.test']]]]]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => []]]]]);
        });

        try {
            app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test');
            $this->fail('Expected the unrelated domain conflict to stop launch.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('already belongs to cPanel account "otherusr"', $exception->getMessage());
        }
        Http::assertNotSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain' || str_contains($request->url(), '/modifyacct'));
    }

    public function test_http_success_with_api_level_addon_failure_surfaces_the_sanitized_cpanel_error(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'private-whm-token']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);
        Log::spy();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain') return Http::response([
                'metadata' => ['result' => 1, 'reason' => 'OK'],
                'data' => ['cpanelresult' => [
                    'event' => ['result' => 1],
                    'data' => [[
                        'result' => 0,
                        'reason' => 'Addon domains are disabled. private-whm-token',
                        'errors' => ['Web Server feature unavailable'],
                    ]],
                ]],
            ]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => []]]]]);
        });

        try {
            app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test');
            $this->fail('Expected cPanel API-level failure.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Addon domains are disabled.', $exception->getMessage());
            $this->assertStringContainsString('Web Server feature unavailable', $exception->getMessage());
            $this->assertStringNotContainsString('private-whm-token', $exception->getMessage());
            $this->assertStringContainsString('[REDACTED]', $exception->getMessage());
        }
        Log::shouldHaveReceived('warning')->with('cPanel API 2 addon-domain operation failed.', \Mockery::on(fn ($context) => ! str_contains(json_encode($context) ?: '', 'private-whm-token')))->once();
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($record) => ($record[0]['cpanel_jsonapi_func'] ?? null) === 'listaddondomains')->count());
    }

    public function test_genuine_addon_creation_requires_api_success_then_verifies_the_shared_document_root(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);
        $created = false;

        Http::fake(function ($request) use (&$created) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain') { $created = true; return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['result' => 1, 'reason' => 'Domain created']]]]]); }
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => $created ? [['domain' => 'live.example.test', 'basedir' => 'public_html', 'reldir' => 'home:public_html', 'dir' => '/home/devusr/public_html', 'status' => 0]] : []]]]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => $created ? ['live.example.test'] : []]]]]);
        });

        $result = app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test');
        $this->assertSame('live.example.test', $result['domain']);
        $this->assertSame('devusr', $result['username']);
        $this->assertSame('addon', $result['type']);
        $this->assertSame('public_html', $result['document_root']);
        $this->assertFalse($result['residue_cleaned']);
        Http::assertSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain'
            && $request['cpanel_jsonapi_user'] === 'devusr'
            && $request['newdomain'] === 'live.example.test'
            && $request['dir'] === 'public_html');
        Http::assertSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains');
    }

    public function test_cpanel_event_failure_surfaces_errors_and_never_runs_verification(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 0, 'reason' => 'The API dispatcher rejected the addon operation.'], 'errors' => ['The Domains feature is unavailable.'], 'messages' => ['Contact the hosting provider.'], 'data' => []]]]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => []]]]]);
        });

        try {
            app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test');
            $this->fail('Expected cPanel event failure.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('The API dispatcher rejected the addon operation.', $exception->getMessage());
            $this->assertStringContainsString('The Domains feature is unavailable.', $exception->getMessage());
            $this->assertStringContainsString('Contact the hosting provider.', $exception->getMessage());
        }
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($record) => ($record[0]['cpanel_jsonapi_func'] ?? null) === 'listaddondomains')->count());
    }

    public function test_existing_correct_addon_domain_is_verified_without_creating_a_duplicate(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['domain' => 'live.example.test', 'basedir' => 'public_html']]]]]);

            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => ['live.example.test']]]]]);
        });

        $result = app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test');

        $this->assertSame('live.example.test', $result['domain']);
        $this->assertSame('public_html', $result['document_root']);
        Http::assertNotSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain');
        Http::assertSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains');
    }

    public function test_actual_production_addon_state_in_top_level_api2_response_verifies_and_resumes_go_live(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create([
            'hosting_server_id' => $server->id,
            'external_id' => 'wstest2whawu',
            'username' => 'wstest2whawu',
            'primary_domain' => 'test2-wzaz.sites.web-stamp.co.uk',
            'status' => 'active',
        ]);

        Http::fake(fn ($request) => Http::response([
            'cpanelresult' => [
                'apiversion' => 2,
                'func' => 'listaddondomains',
                'data' => [[
                    'domain' => "  COPPERINGOTS.UK. \n",
                    'dir' => '/public_html',
                    'reldir' => 'home:/public_html/',
                    'basedir' => '/public_html/',
                    'subdomain' => 'ws5e61073be5',
                    'rootdomain' => 'test2-wzaz.sites.web-stamp.co.uk',
                ]],
                'event' => ['result' => 1],
                'module' => 'AddonDomain',
            ],
        ]));

        $result = app(\App\Services\Hosting\KrystalWhmProvider::class)->verifyAddonDomain($server, $account, 'copperingots.uk');

        $this->assertSame('copperingots.uk', $result['domain']);
        $this->assertSame('wstest2whawu', $result['username']);
        $this->assertSame('public_html', $result['document_root']);
        Http::assertSent(fn ($request) => $request['cpanel_jsonapi_user'] === 'wstest2whawu'
            && $request['cpanel_jsonapi_apiversion'] === 2
            && $request['cpanel_jsonapi_module'] === 'AddonDomain'
            && $request['cpanel_jsonapi_func'] === 'listaddondomains');
    }

    public function test_nested_whm_api2_wrapper_and_keyed_addon_data_are_supported(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);

        Http::fake(fn ($request) => Http::response([
            'metadata' => ['result' => 1],
            'data' => ['result' => ['cpanelresult' => [
                'event' => ['result' => '1'],
                'data' => ['addon_key' => [
                    'domain' => 'LIVE.EXAMPLE.TEST.',
                    'dir' => '/home/devusr/public_html/',
                ]],
            ]]],
        ]));

        $result = app(\App\Services\Hosting\KrystalWhmProvider::class)->verifyAddonDomain($server, $account, 'live.example.test');

        $this->assertSame('live.example.test', $result['domain']);
        $this->assertSame('public_html', $result['document_root']);
    }

    public function test_all_supported_public_html_document_root_forms_are_accepted(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'wstest2whawu', 'username' => 'wstest2whawu', 'primary_domain' => 'test2-wzaz.sites.web-stamp.co.uk', 'status' => 'active']);
        $documentRoot = 'public_html';

        Http::fake(function () use (&$documentRoot) {
            return Http::response([
                'metadata' => ['result' => 1],
                'data' => ['cpanelresult' => [
                    'event' => ['result' => 1],
                    'data' => [['domain' => 'copperingots.uk', 'dir' => $documentRoot]],
                ]],
            ]);
        });

        foreach (['public_html', '/public_html', '/home/wstest2whawu/public_html'] as $root) {
            $documentRoot = $root;
            $result = app(\App\Services\Hosting\KrystalWhmProvider::class)->verifyAddonDomain($server, $account, 'copperingots.uk');
            $this->assertSame('public_html', $result['document_root']);
        }
    }

    public function test_different_addon_domain_never_matches_requested_domain(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'wstest2whawu', 'username' => 'wstest2whawu', 'primary_domain' => 'test2-wzaz.sites.web-stamp.co.uk', 'status' => 'active']);

        Http::fake(fn () => Http::response([
            'cpanelresult' => [
                'event' => ['result' => 1],
                'data' => [['domain' => 'not-copperingots.uk', 'dir' => '/public_html']],
            ],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not visible on the expected cPanel account');
        app(\App\Services\Hosting\KrystalWhmProvider::class)->verifyAddonDomain($server, $account, 'copperingots.uk');
    }

    public function test_malformed_addon_domain_list_response_is_safely_rejected(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'wstest2whawu', 'username' => 'wstest2whawu', 'primary_domain' => 'test2-wzaz.sites.web-stamp.co.uk', 'status' => 'active']);

        Http::fake(fn () => Http::response(['unexpected' => ['shape' => true]]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cPanel could not verify the production addon domain.');
        app(\App\Services\Hosting\KrystalWhmProvider::class)->verifyAddonDomain($server, $account, 'copperingots.uk');
    }

    public function test_whm_metadata_wrapper_requires_explicit_metadata_success(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'wstest2whawu', 'username' => 'wstest2whawu', 'primary_domain' => 'test2-wzaz.sites.web-stamp.co.uk', 'status' => 'active']);

        Http::fake(fn () => Http::response([
            'metadata' => ['reason' => 'Unexpected wrapper'],
            'data' => ['cpanelresult' => [
                'event' => ['result' => 1],
                'data' => [['domain' => 'copperingots.uk', 'dir' => '/public_html']],
            ]],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cPanel could not verify the production addon domain.');
        app(\App\Services\Hosting\KrystalWhmProvider::class)->verifyAddonDomain($server, $account, 'copperingots.uk');
    }

    public function test_direct_api2_addon_list_failure_surfaces_only_sanitized_details(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'private-whm-token']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'wstest2whawu', 'username' => 'wstest2whawu', 'primary_domain' => 'test2-wzaz.sites.web-stamp.co.uk', 'status' => 'active']);

        Http::fake(fn () => Http::response([
            'cpanelresult' => [
                'event' => ['result' => 0, 'reason' => 'Addon domain listing denied. private-whm-token'],
                'errors' => ['Domains feature unavailable.'],
                'data' => [],
            ],
        ]));

        try {
            app(\App\Services\Hosting\KrystalWhmProvider::class)->verifyAddonDomain($server, $account, 'copperingots.uk');
            $this->fail('Expected the API2 list failure to stop verification.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Addon domain listing denied.', $exception->getMessage());
            $this->assertStringContainsString('Domains feature unavailable.', $exception->getMessage());
            $this->assertStringContainsString('[REDACTED]', $exception->getMessage());
            $this->assertStringNotContainsString('private-whm-token', $exception->getMessage());
        }
    }

    public function test_delayed_addon_visibility_retries_without_creating_a_duplicate_domain(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);
        $created = false;
        $visible = false;
        $createCalls = 0;

        Http::fake(function ($request) use (&$created, &$visible, &$createCalls) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain') { $createCalls++; $created = true; return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]); }
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => $visible ? [['domain' => 'live.example.test', 'basedir' => 'public_html']] : []]]]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => ($created && $visible) ? ['live.example.test'] : []]]]]);
        });

        try {
            app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test');
            $this->fail('Expected delayed verification to stop the first attempt.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('not visible', $exception->getMessage());
        }
        $visible = true;
        $result = app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test');
        $this->assertSame('live.example.test', $result['domain']);
        $this->assertSame(1, $createCalls);
    }

    public function test_owned_residual_internal_subdomain_is_safely_deleted_before_addon_creation(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);
        $label = 'ws'.substr(hash('sha256', 'live.example.test'), 0, 10);
        $internal = $label.'.dev.example.test';
        $subdomains = [['domain' => $internal, 'rootdomain' => 'dev.example.test', 'subdomain' => $label, 'basedir' => 'public_html', 'reldir' => 'home:public_html', 'dir' => '/home/devusr/public_html']];
        $addonCreated = false;
        $calls = [];

        Http::fake(function ($request) use (&$subdomains, &$addonCreated, &$calls, $internal) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains') { $calls[] = 'inspect'; return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => $subdomains]]]); }
            if (($request['cpanel_jsonapi_func'] ?? null) === 'delsubdomain') { $calls[] = 'delete'; $this->assertSame($internal, $request['domain']); $this->assertSame('devusr', $request['cpanel_jsonapi_user']); $subdomains = []; return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['result' => 1, 'reason' => 'Removed']]]]]); }
            if (($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain') { $calls[] = 'create'; $addonCreated = true; return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['result' => 1]]]]]); }
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') { $calls[] = 'verify'; return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => $addonCreated ? [['domain' => 'live.example.test', 'basedir' => 'public_html']] : []]]]); }
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => []]]]]);
        });

        $result = app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test', 'dev.example.test');

        $this->assertTrue($result['residue_cleaned']);
        $this->assertSame($internal, $result['internal_subdomain']);
        $this->assertSame(['verify', 'inspect', 'delete', 'create', 'verify'], $calls);
    }

    public function test_dns_entry_conflict_reconciles_owned_residue_and_retries_creation_once(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);
        $label = 'ws'.substr(hash('sha256', 'live.example.test'), 0, 10);
        $internal = $label.'.dev.example.test';
        $subdomains = [];
        $createCalls = 0;
        $deleteCalls = 0;

        Http::fake(function ($request) use (&$subdomains, &$createCalls, &$deleteCalls, $label, $internal) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => $subdomains]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'delsubdomain') { $deleteCalls++; $subdomains = []; return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['result' => 1]]]]]); }
            if (($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain') {
                $createCalls++;
                if ($createCalls === 1) {
                    $subdomains = [['domain' => $internal, 'rootdomain' => 'dev.example.test', 'subdomain' => $label, 'basedir' => 'public_html']];
                    return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['result' => 0, 'reason' => "A DNS entry for the domain \"{$internal}\" already exists."]]]]]);
                }
                return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['result' => 1]]]]]);
            }
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => $createCalls >= 2 ? [['domain' => 'live.example.test', 'basedir' => 'public_html']] : []]]]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => []]]]]);
        });

        $result = app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test', 'dev.example.test');

        $this->assertTrue($result['residue_cleaned']);
        $this->assertSame(2, $createCalls);
        $this->assertSame(1, $deleteCalls);
    }

    public function test_unrelated_similar_subdomain_is_never_deleted(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);
        $label = 'ws'.substr(hash('sha256', 'live.example.test'), 0, 10);
        $deleteCalls = 0;
        $addonCreated = false;

        Http::fake(function ($request) use (&$deleteCalls, &$addonCreated, $label) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => $addonCreated ? [['domain' => 'live.example.test', 'basedir' => 'public_html']] : []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['domain' => $label.'x.dev.example.test', 'rootdomain' => 'dev.example.test', 'subdomain' => $label.'x', 'basedir' => 'public_html']]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'delsubdomain') { $deleteCalls++; return Http::response([], 500); }
            if (($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain') { $addonCreated = true; return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['result' => 1]]]]]); }
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => []]]]]);
        });

        app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test', 'dev.example.test');

        $this->assertSame(0, $deleteCalls);
    }

    public function test_unproven_residue_requires_manual_review_without_deletion_or_creation(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);
        $label = 'ws'.substr(hash('sha256', 'live.example.test'), 0, 10);
        $internal = $label.'.dev.example.test';

        Http::fake(function ($request) use ($label, $internal) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['domain' => $internal, 'rootdomain' => 'other.example.test', 'subdomain' => $label, 'basedir' => 'public_html']]]]]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => []]]]]);
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot prove it is owned residue');
        try {
            app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test', 'dev.example.test');
        } finally {
            Http::assertNotSent(fn ($request) => in_array(($request['cpanel_jsonapi_func'] ?? null), ['delsubdomain', 'addaddondomain'], true));
        }
    }

    public function test_residual_subdomain_without_go_live_identity_is_never_deleted(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);
        $label = 'ws'.substr(hash('sha256', 'live.example.test'), 0, 10);
        $internal = $label.'.dev.example.test';

        Http::fake(function ($request) use ($label, $internal) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['domain' => $internal, 'rootdomain' => 'dev.example.test', 'subdomain' => $label, 'basedir' => 'public_html']]]]]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => []]]]]);
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no launch identity was supplied');
        try {
            app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test');
        } finally {
            Http::assertNotSent(fn ($request) => in_array(($request['cpanel_jsonapi_func'] ?? null), ['delsubdomain', 'addaddondomain'], true));
        }
    }

    public function test_dns_conflict_on_another_account_is_not_cleaned(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);
        $label = 'ws'.substr(hash('sha256', 'live.example.test'), 0, 10);
        $internal = $label.'.dev.example.test';

        Http::fake(function ($request) use ($internal) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['result' => 0, 'reason' => "A DNS entry for the domain \"{$internal}\" already exists."]]]]]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => []]]]]);
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not prove it is owned residue');
        try {
            app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test', 'dev.example.test');
        } finally {
            Http::assertNotSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'delsubdomain');
            Http::assertSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains' && $request['cpanel_jsonapi_user'] === 'devusr');
        }
    }

    public function test_development_domain_mismatch_stops_before_any_cleanup_target_is_touched(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => []]]]]);
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not match the cPanel primary domain');
        try {
            app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test', 'different.example.test');
        } finally {
            Http::assertNotSent(fn ($request) => in_array(($request['cpanel_jsonapi_func'] ?? null), ['delsubdomain', 'addaddondomain'], true));
        }
    }

    public function test_cleanup_succeeds_but_single_retry_surfaces_real_sanitized_cpanel_error(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'private-token']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);
        $label = 'ws'.substr(hash('sha256', 'live.example.test'), 0, 10);
        $internal = $label.'.dev.example.test';
        $subdomains = [];
        $createCalls = 0;

        Http::fake(function ($request) use (&$subdomains, &$createCalls, $label, $internal) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listaddondomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => []]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'listsubdomains') return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => $subdomains]]]);
            if (($request['cpanel_jsonapi_func'] ?? null) === 'delsubdomain') { $subdomains = []; return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['result' => 1]]]]]); }
            if (($request['cpanel_jsonapi_func'] ?? null) === 'addaddondomain') {
                $createCalls++;
                if ($createCalls === 1) {
                    $subdomains = [['domain' => $internal, 'rootdomain' => 'dev.example.test', 'subdomain' => $label, 'basedir' => 'public_html']];
                    return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['result' => 0, 'reason' => "A DNS entry for the domain \"{$internal}\" already exists."]]]]]);
                }
                return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['result' => 0, 'reason' => 'Addon domain creation denied. private-token']]]]]);
            }
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => []]]]]);
        });

        try {
            app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test', 'dev.example.test');
            $this->fail('Expected the bounded retry to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Addon domain creation denied.', $exception->getMessage());
            $this->assertStringNotContainsString('private-token', $exception->getMessage());
            $this->assertStringContainsString('[REDACTED]', $exception->getMessage());
        }
        $this->assertSame(2, $createCalls);
    }

    public function test_existing_addon_domain_with_a_different_document_root_is_rejected(): void
    {
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'devusr', 'username' => 'devusr', 'primary_domain' => 'dev.example.test', 'status' => 'active']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/listaccts')) return Http::response(['metadata' => ['result' => 1], 'data' => ['acct' => [['user' => 'devusr', 'domain' => 'dev.example.test', 'suspended' => 0]]]]);
            if ((int) ($request['cpanel_jsonapi_apiversion'] ?? 0) === 2) return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => [['domain' => 'live.example.test', 'basedir' => 'live.example.test', 'reldir' => 'home:live.example.test', 'dir' => '/home/devusr/live.example.test']]]]]);
            return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'dev.example.test', 'addon_domains' => ['live.example.test']]]]]);
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not use the development website document root');
        app(\App\Services\Hosting\KrystalWhmProvider::class)->ensureAddonDomain($server, $account, 'live.example.test');
    }

    private function fixtures(): array
    {
        $admin = $this->user('admin');
        $customer = Customer::create(['name' => 'Client', 'email' => fake()->unique()->safeEmail(), 'billing_address' => '1 Test Road']);
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'mock', 'metadata' => ['development_base_domain' => 'dev.web-stamp.co.uk']]);
        $package = HostingPackage::create(['hosting_server_id' => $server->id, 'external_id' => 'standard', 'name' => 'Standard']);
        return [$admin, $customer, $server, $package];
    }

    private function monitoringLaunchFixture(array $websiteOverrides = []): array
    {
        $admin = $this->user('admin');
        $customer = Customer::create(['name' => 'Client', 'email' => fake()->unique()->safeEmail(), 'billing_address' => '1 Test Road']);
        $server = HostingServer::create(['name' => 'Krystal', 'provider' => 'krystal', 'api_type' => 'whm', 'hostname' => 'whm.example.test', 'credentials' => ['username' => 'reseller', 'token' => 'whm-secret']]);
        $account = HostingAccount::create(['hosting_server_id' => $server->id, 'external_id' => 'wstest2whawu', 'username' => 'wstest2whawu', 'primary_domain' => 'test2-wzaz.sites.web-stamp.co.uk', 'assigned_ip' => '192.0.2.10', 'status' => 'active', 'last_synced_at' => now(), 'automation_password_encrypted' => 'cpanel-secret']);
        $website = Website::create([...[
            'customer_id' => $customer->id,
            'hosting_server_id' => $server->id,
            'hosting_account_id' => $account->id,
            'name' => 'Copper Ingots',
            'domain' => 'test2-wzaz.sites.web-stamp.co.uk',
            'current_domain' => 'test2-wzaz.sites.web-stamp.co.uk',
            'development_domain' => 'test2-wzaz.sites.web-stamp.co.uk',
            'production_domain' => 'copperingots.uk',
            'environment' => 'development',
            'login_url' => 'https://test2-wzaz.sites.web-stamp.co.uk/wp-admin/',
            'hosting_enabled' => true,
            'wordpress_enabled' => true,
            'monitoring_enabled' => true,
            'agent_token_hash' => hash('sha256', 'existing-agent-token'),
            'agent_token_encrypted' => 'existing-agent-token',
        ], ...$websiteOverrides]);
        $run = WebsiteLaunchRun::create([
            'public_id' => (string) Str::uuid(),
            'website_id' => $website->id,
            'hosting_account_id' => $account->id,
            'initiated_by_user_id' => $admin->id,
            'idempotency_key' => 'monitoring-launch-'.Str::random(8),
            'development_domain' => 'test2-wzaz.sites.web-stamp.co.uk',
            'production_domain' => 'copperingots.uk',
            'expected_ip' => '192.0.2.10',
            'state' => 'failed',
            'failed_step' => 'verify_production',
            'safe_error' => 'The monitoring plugin has not reconnected.',
        ]);
        foreach (['preflight', 'attach_production_domain', 'verify_production_domain', 'check_dns', 'trigger_autossl', 'check_ssl', 'migrate_wordpress', 'verify_production', 'redirect_development_domain'] as $name) {
            $run->steps()->create([
                'step' => $name,
                'status' => $name === 'verify_production' ? 'failed' : ($name === 'redirect_development_domain' ? 'pending' : 'complete'),
                'attempts' => $name === 'verify_production' ? 1 : ($name === 'redirect_development_domain' ? 0 : 1),
                'completed_at' => $name === 'redirect_development_domain' ? null : now(),
            ]);
        }

        return [$run, $website, $account];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());
        return $user;
    }
}

class LaunchMonitoringSshRunner implements SshCommandRunner
{
    public array $commands = [];
    private bool $pluginInstalled = false;
    private string $htaccess = "# php -- BEGIN cPanel-generated handler\nAddHandler application/x-httpd-ea-php83 .php\n# php -- END cPanel-generated handler\n";

    public function __construct(private string $productionUrl) {}

    public function run(HostingServer $server, HostingAccount $account, string $password, string $command, int $timeout = 60): array
    {
        $this->commands[] = $command;
        if (str_contains($command, "'option' 'get' 'siteurl'") || str_contains($command, "'option' 'get' 'home'")) $output = $this->productionUrl;
        elseif (str_contains($command, "'db' 'tables'")) $output = 'wp_options';
        elseif (str_contains($command, "'plugin' 'is-installed'")) return ['exit_code' => $this->pluginInstalled ? 0 : 1, 'stdout' => '', 'stderr' => ''];
        elseif (str_contains($command, '__WEBSTAMP_HTACCESS__')) $output = '__WEBSTAMP_HTACCESS__'.base64_encode($this->htaccess);
        elseif (str_contains($command, '__WEBSTAMP_HTACCESS_UPDATED__')) $output = '__WEBSTAMP_HTACCESS_UPDATED__';
        elseif (str_contains($command, '__WEBSTAMP_HTACCESS_VERIFIED__')) $output = '__WEBSTAMP_HTACCESS_VERIFIED__';
        elseif (str_contains($command, '__WEBSTAMP_AGENT_INSTALLED__')) { $this->pluginInstalled = true; $output = '__WEBSTAMP_AGENT_INSTALLED__'; }
        elseif (str_contains($command, '__WEBSTAMP_REDIRECT_READY__')) $output = '__WEBSTAMP_REDIRECT_READY__';
        else $output = 'Success';

        return ['exit_code' => 0, 'stdout' => $output, 'stderr' => ''];
    }
}

class FakeLaunchSslInspector implements SslInspector
{
    public function inspect(string $domain): array
    {
        return ['valid' => true, 'hostname_match' => true, 'status' => 'active', 'issuer' => 'Test CA', 'expires_at' => now()->addMonth()->toIso8601String()];
    }
}
