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
            if (str_contains($request->url(), '/cpanel') && (int) $request['cpanel_jsonapi_apiversion'] === 2) return Http::response(['metadata' => ['result' => 1], 'data' => ['cpanelresult' => ['event' => ['result' => 1], 'data' => $addon ? [['domain' => 'site.test', 'basedir' => 'public_html', 'reldir' => 'home:public_html', 'dir' => '/home/devusr/public_html']] : []]]]);
            if (str_contains($request->url(), '/cpanel')) return Http::response(['metadata' => ['result' => 1], 'data' => ['result' => ['data' => ['main_domain' => 'site-ab12.dev.web-stamp.co.uk', 'addon_domains' => $addon ? ['site.test'] : []]]]]);
            if (str_contains($request->url(), '/uapi_cpanel')) return Http::response(['metadata' => ['result' => 1], 'data' => ['uapi' => ['status' => 1, 'errors' => null, 'messages' => null, 'data' => null]]]);
            return Http::response('ok', 200);
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
            if (str_starts_with($url, 'https://')) return Http::response('', 200);
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

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());
        return $user;
    }
}
