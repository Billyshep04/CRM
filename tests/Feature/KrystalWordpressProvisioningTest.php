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

    public function test_ssh_probe_returns_safe_success_and_safe_failure(): void
    {
        $service = new KrystalWordpressProvisioner(new RecordingSshRunner);
        $this->assertTrue($service->testSsh($this->server(), $this->account(), 'not-returned')['connected']);
        $failed = new KrystalWordpressProvisioner(new RecordingSshRunner(['printf connected' => ['exit_code' => 1, 'output' => 'secret server output']]));
        try { $failed->testSsh($this->server(), $this->account(), 'not-returned'); $this->fail('Expected failure.'); }
        catch (RuntimeException $exception) { $this->assertSame('SSH connection failed. Check Shell Access and cPanel credentials.', $exception->getMessage()); }
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

    public function test_ssl_requires_valid_certificates_for_root_and_www(): void
    {
        $active = new FakeSslInspector(['valid' => true, 'hostname_match' => true, 'issuer' => 'Let’s Encrypt', 'expires_at' => now()->addDays(60)->toIso8601String(), 'error' => null]);
        $this->assertTrue((new ProvisioningSslService($active))->inspect('example.test')['ready']);
        $pending = new FakeSslInspector(['valid' => false, 'hostname_match' => false, 'issuer' => null, 'expires_at' => null, 'error' => 'pending']);
        $this->assertFalse((new ProvisioningSslService($pending))->inspect('example.test')['ready']);
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
