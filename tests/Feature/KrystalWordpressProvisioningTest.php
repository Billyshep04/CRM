<?php

namespace Tests\Feature;

use App\Contracts\DnsResolver;
use App\Contracts\SshCommandRunner;
use App\Contracts\SslInspector;
use App\Models\HostingAccount;
use App\Models\HostingPackage;
use App\Models\HostingServer;
use App\Services\Hosting\KrystalWordpressProvisioner;
use App\Services\Hosting\ProvisioningDnsService;
use App\Services\Hosting\ProvisioningSslService;
use App\Services\Hosting\ProvisioningHttpService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class KrystalWordpressProvisioningTest extends TestCase
{
    public function test_every_wp_cli_command_uses_the_required_php_wrapper_and_quotes_arguments(): void
    {
        $runner = new RecordingSshRunner;
        $service = new KrystalWordpressProvisioner($runner);
        $command = $service->wpCliCommand(['option', 'update', 'blogname', 'A title; touch /tmp/no']);
        $this->assertStringStartsWith('cd ~/public_html && php -d disable_functions= "$(which wp)" ', $command);
        $this->assertStringContainsString("'A title; touch /tmp/no'", $command);
    }

    public function test_shell_arguments_remain_safe_without_php_escape_shell_functions(): void
    {
        $service = new KrystalWordpressProvisioner(new RecordingSshRunner);
        $command = $service->wpCliCommand(['option', 'update', 'blogname', "O'Brien; touch /tmp/no"]);

        $this->assertStringContainsString("'O'\"'\"'Brien; touch /tmp/no'", $command);
        $this->assertSame(1, substr_count($command, 'touch /tmp/no'));
    }

    public function test_wordpress_download_refuses_to_overwrite_existing_public_html(): void
    {
        $runner = new RecordingSshRunner(['test -f public_html/wp-load.php' => ['exit_code' => 1, 'output' => ''], 'find public_html' => ['exit_code' => 0, 'output' => "index.php\nassets\n"]]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('public_html contains an existing website');
        (new KrystalWordpressProvisioner($runner))->downloadWordpress($this->server(), $this->account(), 'not-logged');
    }

    public function test_shell_access_is_a_hard_prerequisite_for_live_wordpress_setup(): void
    {
        $package = new HostingPackage(['name' => 'No shell', 'shell_access' => false]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shell Access is not enabled');
        (new KrystalWordpressProvisioner(new RecordingSshRunner))->validatePrerequisites($package, true);
    }

    public function test_unknown_package_shell_metadata_does_not_block_the_real_ssh_check(): void
    {
        $package = new HostingPackage(['name' => 'Shell metadata omitted', 'shell_access' => null]);
        $result = (new KrystalWordpressProvisioner(new RecordingSshRunner))->validatePrerequisites($package, true, new HostingServer(['metadata' => ['ssh_host_fingerprint' => str_repeat('a', 64)]]));

        $this->assertSame('requested_and_verified_during_setup', $result['shell_access']);
    }

    public function test_live_prerequisites_require_ssh_trust_before_hosting_is_created(): void
    {
        $package = new HostingPackage(['name' => 'Shell enabled', 'shell_access' => true]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSH host verification is not configured');
        (new KrystalWordpressProvisioner(new RecordingSshRunner))->validatePrerequisites($package, true, new HostingServer);
    }

    public function test_database_config_and_install_commands_are_unattended_and_idempotent(): void
    {
        $runner = new RecordingSshRunner([
            'uapi --output=json' => ['exit_code' => 0, 'output' => '{"result":{"status":1}}'],
            'test -f public_html/wp-config.php' => ['exit_code' => 1, 'output' => ''],
            "'core' 'is-installed'" => ['exit_code' => 1, 'output' => ''],
        ]);
        $service = new KrystalWordpressProvisioner($runner);
        $server = $this->server(); $account = $this->account();
        $service->createDatabase($server, $account, 'cpanel-secret', 'customerone_wp');
        $service->createDatabaseUser($server, $account, 'cpanel-secret', 'customerone_wpuser', 'database-secret');
        $service->grantPrivileges($server, $account, 'cpanel-secret', 'customerone_wp', 'customerone_wpuser');
        $service->createConfig($server, $account, 'cpanel-secret', ['name' => 'customerone_wp', 'user' => 'customerone_wpuser', 'password' => 'database-secret']);
        $service->install($server, $account, 'cpanel-secret', ['url' => 'https://example.test', 'title' => 'Example', 'admin_username' => 'webstamp_admin', 'admin_password' => 'wordpress-secret', 'admin_email' => 'admin@example.test']);
        $commands = implode("\n", $runner->commands);
        $this->assertStringContainsString("'Mysql' 'create_database'", $commands);
        $this->assertStringContainsString("'Mysql' 'create_user'", $commands);
        $this->assertStringContainsString("'Mysql' 'set_privileges_on_database'", $commands);
        $this->assertStringContainsString("php -d disable_functions= \"$(which wp)\" 'config' 'create'", $commands);
        $this->assertStringContainsString("php -d disable_functions= \"$(which wp)\" 'core' 'install'", $commands);
    }

    public function test_database_names_use_cpanels_reported_prefix_and_unprefixed_create_arguments(): void
    {
        $runner = new RecordingSshRunner([
            "'Mysql' 'get_restrictions'" => ['exit_code' => 0, 'output' => '{"result":{"status":1,"data":{"prefix":"customer_","max_database_name_length":64,"max_username_length":32}}}'],
            'uapi --output=json' => ['exit_code' => 0, 'output' => '{"result":{"status":1}}'],
        ]);
        $service = new KrystalWordpressProvisioner($runner);
        $names = $service->databaseNames($this->server(), $this->account(), 'cpanel-secret');

        $this->assertSame('customer_wp', $names['database']);
        $this->assertSame('customer_wpuser', $names['database_user']);
        $service->createDatabase($this->server(), $this->account(), 'cpanel-secret', $names['database'], $names['database_api_name']);
        $service->createDatabaseUser($this->server(), $this->account(), 'cpanel-secret', $names['database_user'], 'database-secret', $names['database_user_api_name']);
        $commands = implode("\n", $runner->commands);
        $this->assertStringContainsString("'name=wp'", $commands);
        $this->assertStringContainsString("'name=wpuser'", $commands);
        $this->assertStringNotContainsString("'name=customer_wp'", $commands);
    }

    public function test_uapi_failure_exposes_only_safe_actionable_categories(): void
    {
        $runner = new RecordingSshRunner([
            'uapi --output=json' => ['exit_code' => 1, 'output' => '{"result":{"status":0,"errors":["The maximum number of databases (1) has been reached (password database-secret)"]}}'],
        ]);
        try {
            (new KrystalWordpressProvisioner($runner))->createDatabaseUser($this->server(), $this->account(), 'cpanel-secret', 'customer_wpuser', 'database-secret', 'wpuser');
            $this->fail('Expected a UAPI failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The cPanel account has reached its MySQL database or user quota.', $exception->getMessage());
            $this->assertStringNotContainsString('database-secret', $exception->getMessage());
        }
    }

    public function test_ssh_probe_returns_safe_success_and_safe_failure(): void
    {
        $service = new KrystalWordpressProvisioner(new RecordingSshRunner);
        $this->assertTrue($service->testSsh($this->server(), $this->account(), 'not-returned')['connected']);
        $failed = new KrystalWordpressProvisioner(new RecordingSshRunner(['printf connected' => ['exit_code' => 1, 'output' => 'secret server output']]));
        try { $failed->testSsh($this->server(), $this->account(), 'not-returned'); $this->fail('Expected failure.'); }
        catch (RuntimeException $exception) { $this->assertSame('SSH connection failed. Check Shell Access and cPanel credentials.', $exception->getMessage()); }
    }

    public function test_ssh_probe_checks_all_tools_needed_by_later_steps(): void
    {
        $service = new KrystalWordpressProvisioner(new RecordingSshRunner(['for tool in php wp uapi' => ['exit_code' => 1, 'output' => 'not returned']]));
        try { $service->testSsh($this->server(), $this->account(), 'not-returned'); $this->fail('Expected missing tools failure.'); }
        catch (RuntimeException $exception) { $this->assertSame('SSH connected, but PHP, WP-CLI, or cPanel UAPI is unavailable for this account.', $exception->getMessage()); }
    }

    public function test_dns_reports_correct_wrong_and_missing_records_and_detects_provider(): void
    {
        $correct = new FakeDnsResolver(['example.test' => ['1.2.3.4'], 'www.example.test' => ['1.2.3.4']], [], ['example.test' => ['amy.ns.cloudflare.com']]);
        $result = (new ProvisioningDnsService($correct))->inspect('example.test', '1.2.3.4');
        $this->assertTrue($result['ready']);
        $this->assertSame('cloudflare', $result['provider']['key']);

        $wrong = new FakeDnsResolver(['example.test' => ['5.6.7.8']], [], []);
        $result = (new ProvisioningDnsService($wrong))->inspect('example.test', '1.2.3.4');
        $this->assertFalse($result['ready']);
        $this->assertSame('incorrect', $result['root']['status']);
        $this->assertSame('missing', $result['www']['status']);
    }

    public function test_subdomain_dns_only_requires_the_exact_subdomain_record(): void
    {
        $resolver = new FakeDnsResolver(['dev4.web-stamp.co.uk' => ['1.2.3.4']], [], []);
        $result = (new ProvisioningDnsService($resolver))->inspect('dev4.web-stamp.co.uk', '1.2.3.4');

        $this->assertTrue($result['ready']);
        $this->assertFalse($result['www_required']);
        $this->assertSame('not_required', $result['www']['status']);
    }

    public function test_ssl_requires_valid_certificates_for_root_and_www(): void
    {
        $active = new FakeSslInspector(['valid' => true, 'hostname_match' => true, 'issuer' => 'Let’s Encrypt', 'expires_at' => now()->addDays(60)->toIso8601String(), 'error' => null]);
        $this->assertTrue((new ProvisioningSslService($active))->inspect('example.test')['ready']);
        $pending = new FakeSslInspector(['valid' => false, 'hostname_match' => false, 'issuer' => null, 'expires_at' => null, 'error' => 'pending']);
        $this->assertFalse((new ProvisioningSslService($pending))->inspect('example.test')['ready']);
    }

    public function test_subdomain_ssl_only_requires_the_exact_subdomain_certificate(): void
    {
        $active = new FakeSslInspector(['valid' => true, 'hostname_match' => true, 'issuer' => 'Let’s Encrypt', 'expires_at' => now()->addDays(60)->toIso8601String(), 'error' => null]);
        $result = (new ProvisioningSslService($active))->inspect('dev4.web-stamp.co.uk');

        $this->assertTrue($result['ready']);
        $this->assertFalse($result['www_required']);
    }

    public function test_final_http_check_verifies_http_https_and_wordpress_admin_without_storing_bodies(): void
    {
        Http::fake([
            'http://example.test/*' => Http::response('', 301, ['Location' => 'https://example.test/']),
            'https://example.test/*' => Http::response('', 200),
        ]);
        $result = (new ProvisioningHttpService)->inspect('example.test');
        $this->assertTrue($result['ready']);
        $this->assertArrayNotHasKey('body', $result['checks']['https_home']);
        $this->assertGreaterThanOrEqual(3, count(Http::recorded()));
    }

    private function server(): HostingServer { return new HostingServer(['name' => 'Krystal', 'hostname' => 'server.test']); }
    private function account(): HostingAccount { return new HostingAccount(['username' => 'customerone', 'primary_domain' => 'example.test']); }
}

class RecordingSshRunner implements SshCommandRunner
{
    public array $commands = [];
    public function __construct(private array $responses = []) {}
    public function run(HostingServer $server, HostingAccount $account, string $password, string $command, int $timeout = 60): array
    {
        $this->commands[] = $command;
        foreach ($this->responses as $fragment => $response) if (str_contains($command, $fragment)) return $response;
        return ['exit_code' => 0, 'output' => ''];
    }
}

class FakeDnsResolver implements DnsResolver
{
    public function __construct(private array $a, private array $cname, private array $ns) {}
    public function aRecords(string $host): array { return $this->a[$host] ?? []; }
    public function cnameRecords(string $host): array { return $this->cname[$host] ?? []; }
    public function nameservers(string $host): array { return $this->ns[$host] ?? []; }
}

class FakeSslInspector implements SslInspector
{
    public function __construct(private array $result) {}
    public function inspect(string $host): array { return $this->result; }
}
