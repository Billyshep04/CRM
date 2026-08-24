<?php

namespace Tests\Feature;

use App\Contracts\DnsResolver;
use App\Contracts\SshCommandRunner;
use App\Contracts\CpanelUapiClient;
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
        $service = new KrystalWordpressProvisioner($runner, new RecordingCpanelUapiClient);
        $command = $service->wpCliCommand(['option', 'update', 'blogname', 'A title; touch /tmp/no']);
        $this->assertStringStartsWith('cd ~/public_html && php -d disable_functions= "$(which wp)" ', $command);
        $this->assertStringContainsString("'A title; touch /tmp/no'", $command);
    }

    public function test_shell_arguments_remain_safe_without_php_escape_shell_functions(): void
    {
        $service = new KrystalWordpressProvisioner(new RecordingSshRunner, new RecordingCpanelUapiClient);
        $command = $service->wpCliCommand(['option', 'update', 'blogname', "O'Brien; touch /tmp/no"]);

        $this->assertStringContainsString("'O'\"'\"'Brien; touch /tmp/no'", $command);
        $this->assertSame(1, substr_count($command, 'touch /tmp/no'));
    }

    public function test_wordpress_download_refuses_to_overwrite_existing_public_html(): void
    {
        $runner = new RecordingSshRunner(['test -f public_html/wp-load.php' => ['exit_code' => 1, 'stdout' => '', 'stderr' => ''], 'find public_html' => ['exit_code' => 0, 'stdout' => "index.php\nassets\n", 'stderr' => '']]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('public_html contains an existing website');
        (new KrystalWordpressProvisioner($runner, new RecordingCpanelUapiClient))->downloadWordpress($this->server(), $this->account(), 'not-logged');
    }

    public function test_shell_access_is_a_hard_prerequisite_for_live_wordpress_setup(): void
    {
        $package = new HostingPackage(['name' => 'No shell', 'shell_access' => false]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shell Access is not enabled');
        (new KrystalWordpressProvisioner(new RecordingSshRunner, new RecordingCpanelUapiClient))->validatePrerequisites($package, true);
    }

    public function test_unknown_package_shell_metadata_does_not_block_the_real_ssh_check(): void
    {
        $package = new HostingPackage(['name' => 'Shell metadata omitted', 'shell_access' => null]);
        $result = (new KrystalWordpressProvisioner(new RecordingSshRunner, new RecordingCpanelUapiClient))->validatePrerequisites($package, true, new HostingServer(['metadata' => ['ssh_host_fingerprint' => str_repeat('a', 64)]]));

        $this->assertSame('requested_and_verified_during_setup', $result['shell_access']);
    }

    public function test_live_prerequisites_require_ssh_trust_before_hosting_is_created(): void
    {
        $package = new HostingPackage(['name' => 'Shell enabled', 'shell_access' => true]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSH host verification is not configured');
        (new KrystalWordpressProvisioner(new RecordingSshRunner, new RecordingCpanelUapiClient))->validatePrerequisites($package, true, new HostingServer);
    }

    public function test_database_config_and_install_commands_are_unattended_and_idempotent(): void
    {
        $runner = new RecordingSshRunner([
            'test -f public_html/wp-config.php' => ['exit_code' => 1, 'stdout' => '', 'stderr' => ''],
            "'core' 'is-installed'" => ['exit_code' => 1, 'stdout' => '', 'stderr' => ''],
        ]);
        $uapi = new RecordingCpanelUapiClient;
        $service = new KrystalWordpressProvisioner($runner, $uapi);
        $server = $this->server(); $account = $this->account();
        $service->createDatabase($server, $account, 'cpanel-secret', 'customerone_wp');
        $service->createDatabaseUser($server, $account, 'cpanel-secret', 'customerone_wpuser', 'database-secret');
        $service->grantPrivileges($server, $account, 'cpanel-secret', 'customerone_wp', 'customerone_wpuser');
        $service->createConfig($server, $account, 'cpanel-secret', ['name' => 'customerone_wp', 'user' => 'customerone_wpuser', 'password' => 'database-secret']);
        $service->install($server, $account, 'cpanel-secret', ['url' => 'https://example.test', 'title' => 'Example', 'admin_username' => 'webstamp_admin', 'admin_password' => 'wordpress-secret', 'admin_email' => 'admin@example.test']);
        $commands = implode("\n", $runner->commands);
        $this->assertSame(['create_database', 'create_user', 'set_privileges_on_database'], array_column($uapi->calls, 'function'));
        $this->assertStringContainsString("php -d disable_functions= \"$(which wp)\" 'config' 'create'", $commands);
        $this->assertStringContainsString("php -d disable_functions= \"$(which wp)\" 'core' 'install'", $commands);
    }

    public function test_retry_reuses_matching_wp_config_and_existing_wordpress_installation(): void
    {
        $runner = new RecordingSshRunner([
            'test -f public_html/wp-config.php' => ['exit_code' => 0, 'stdout' => '', 'stderr' => ''],
            "'config' 'get' 'DB_NAME'" => ['exit_code' => 0, 'stdout' => "customerone_wp\n", 'stderr' => ''],
            "'config' 'get' 'DB_USER'" => ['exit_code' => 0, 'stdout' => "customerone_wpuser\n", 'stderr' => ''],
            "'core' 'is-installed'" => ['exit_code' => 0, 'stdout' => '', 'stderr' => ''],
        ]);
        $service = new KrystalWordpressProvisioner($runner, new RecordingCpanelUapiClient);
        $service->createConfig($this->server(), $this->account(), 'not-logged', [
            'name' => 'customerone_wp', 'user' => 'customerone_wpuser', 'password' => 'not-logged',
        ]);
        $service->install($this->server(), $this->account(), 'not-logged', [
            'url' => 'https://example.test', 'title' => 'Example', 'admin_username' => 'webstamp_admin',
            'admin_password' => 'not-logged', 'admin_email' => 'admin@example.test',
        ]);

        $commands = implode("\n", $runner->commands);
        $this->assertStringNotContainsString("'config' 'create'", $commands);
        $this->assertStringNotContainsString("'core' 'install'", $commands);
        $this->assertStringContainsString("'config' 'get' 'DB_NAME'", $commands);
        $this->assertStringContainsString("'config' 'get' 'DB_USER'", $commands);
    }

    public function test_entire_database_chain_uses_cpanels_authoritative_prefix(): void
    {
        $runner = new RecordingSshRunner(['test -f public_html/wp-config.php' => ['exit_code' => 1, 'stdout' => '', 'stderr' => '']]);
        $uapi = new RecordingCpanelUapiClient([
            'get_restrictions' => ['result' => ['status' => 1, 'data' => ['prefix' => 'unusualprefix_', 'max_database_name_length' => 64, 'max_username_length' => 32]]],
        ]);
        $service = new KrystalWordpressProvisioner($runner, $uapi);
        $names = $service->databaseNames($this->server(), $this->account(), 'cpanel-secret');

        $this->assertSame('customerone', $this->account()->username);
        $this->assertSame('unusualprefix_wp', $names['database']);
        $this->assertSame('unusualprefix_wpuser', $names['database_user']);
        $this->assertSame('wp', $names['database_api_name']);
        $this->assertSame('wpuser', $names['database_user_api_name']);
        $service->createDatabase($this->server(), $this->account(), 'cpanel-secret', $names['database'], $names['database_api_name']);
        $service->createDatabaseUser($this->server(), $this->account(), 'cpanel-secret', $names['database_user'], 'database-secret', $names['database_user_api_name']);
        $service->grantPrivileges($this->server(), $this->account(), 'cpanel-secret', $names['database'], $names['database_user']);
        $service->createConfig($this->server(), $this->account(), 'cpanel-secret', ['name' => $names['database'], 'user' => $names['database_user'], 'password' => 'database-secret']);

        $this->assertSame('unusualprefix_wp', $uapi->calls[1]['parameters']['name']);
        $this->assertSame('unusualprefix_wpuser', $uapi->calls[2]['parameters']['name']);
        $this->assertSame('unusualprefix_wpuser', $uapi->calls[3]['parameters']['user']);
        $this->assertSame('unusualprefix_wp', $uapi->calls[3]['parameters']['database']);
        $this->assertSame('ALL', $uapi->calls[3]['parameters']['privileges']);
        $commands = implode("\n", $runner->commands);
        $this->assertStringContainsString("'--dbname=unusualprefix_wp'", $commands);
        $this->assertStringContainsString("'--dbuser=unusualprefix_wpuser'", $commands);
    }

    public function test_database_naming_fails_safely_when_cpanel_omits_prefix_information(): void
    {
        $service = new KrystalWordpressProvisioner(new RecordingSshRunner, new RecordingCpanelUapiClient([
            'get_restrictions' => ['result' => ['status' => 1, 'data' => ['max_database_name_length' => 64, 'max_username_length' => 32]]],
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not include the required database prefix');
        $service->databaseNames($this->server(), $this->account(), 'cpanel-secret');
    }

    public function test_database_and_user_creation_are_retry_safe_with_the_same_full_names(): void
    {
        $uapi = new RecordingCpanelUapiClient([
            'get_restrictions' => ['result' => ['status' => 1, 'data' => ['prefix' => 'retryprefix_', 'max_database_name_length' => 64, 'max_username_length' => 32]]],
            'create_database' => ['result' => ['status' => 0, 'errors' => ['The database already exists.']]],
            'create_user' => ['result' => ['status' => 0, 'errors' => ['The database user already exists.']]],
        ]);
        $service = new KrystalWordpressProvisioner(new RecordingSshRunner, $uapi);
        $names = $service->databaseNames($this->server(), $this->account(), 'cpanel-secret');

        $service->createDatabase($this->server(), $this->account(), 'cpanel-secret', $names['database']);
        $service->createDatabaseUser($this->server(), $this->account(), 'cpanel-secret', $names['database_user'], 'database-secret');
        $service->createDatabase($this->server(), $this->account(), 'cpanel-secret', $names['database']);
        $service->createDatabaseUser($this->server(), $this->account(), 'cpanel-secret', $names['database_user'], 'database-secret');

        $databaseCalls = collect($uapi->calls)->where('function', 'create_database')->pluck('parameters.name')->all();
        $userCalls = collect($uapi->calls)->where('function', 'create_user')->pluck('parameters.name')->all();
        $this->assertSame(['retryprefix_wp', 'retryprefix_wp'], $databaseCalls);
        $this->assertSame(['retryprefix_wpuser', 'retryprefix_wpuser'], $userCalls);
    }

    public function test_uapi_failure_exposes_only_safe_actionable_categories(): void
    {
        $runner = new RecordingSshRunner;
        $uapi = new RecordingCpanelUapiClient([
            'create_user' => ['result' => ['status' => 0, 'errors' => ['The maximum number of databases (1) has been reached (password database-secret)']]],
        ]);
        try {
            (new KrystalWordpressProvisioner($runner, $uapi))->createDatabaseUser($this->server(), $this->account(), 'cpanel-secret', 'customer_wpuser', 'database-secret', 'wpuser');
            $this->fail('Expected a UAPI failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The cPanel account has reached its MySQL database or user quota.', $exception->getMessage());
            $this->assertStringNotContainsString('database-secret', $exception->getMessage());
        }
    }

    public function test_wordpress_verification_strictly_checks_siteurl_and_home(): void
    {
        $runner = new RecordingSshRunner([
            "'option' 'get' 'siteurl'" => ['exit_code' => 0, 'stdout' => "https://example.test/\n", 'stderr' => ''],
            "'option' 'get' 'home'" => ['exit_code' => 0, 'stdout' => "https://wrong.test\n", 'stderr' => ''],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('installed home URL does not match');
        (new KrystalWordpressProvisioner($runner, new RecordingCpanelUapiClient))
            ->verify($this->server(), $this->account(), 'not-logged', 'https://example.test');
    }

    public function test_temporary_wordpress_verification_diagnostic_is_removed(): void
    {
        $source = file_get_contents(app_path('Services/Hosting/KrystalWordpressProvisioner.php'));

        $this->assertStringNotContainsString('Temporary WordPress', $source);
        $this->assertStringNotContainsString('whoami', $source);
        $this->assertStringNotContainsString('working_directory', $source);
        $this->assertStringNotContainsString('actual_siteurl', $source);
    }

    public function test_ssh_probe_requires_exact_sentinels(): void
    {
        $service = new KrystalWordpressProvisioner(new RecordingSshRunner, new RecordingCpanelUapiClient);
        $this->assertTrue($service->testSsh($this->server(), $this->account(), 'not-returned')['connected']);

        $failed = new KrystalWordpressProvisioner(new RecordingSshRunner([
            '__WEBSTAMP_CONNECTED__' => ['exit_code' => 0, 'stdout' => 'login succeeded without command execution', 'stderr' => ''],
        ]), new RecordingCpanelUapiClient);
        try { $failed->testSsh($this->server(), $this->account(), 'not-returned'); $this->fail('Expected missing sentinel failure.'); }
        catch (RuntimeException $exception) { $this->assertStringContainsString('could not execute shell commands', $exception->getMessage()); }
    }

    public function test_shell_disabled_output_is_rejected_even_with_exit_zero(): void
    {
        $service = new KrystalWordpressProvisioner(new RecordingSshRunner([
            '__WEBSTAMP_CONNECTED__' => ['exit_code' => 0, 'stdout' => 'Shell access is not enabled on your account! If you need shell access please contact support.', 'stderr' => ''],
        ]), new RecordingCpanelUapiClient);

        try { $service->testSsh($this->server(), $this->account(), 'not-returned'); $this->fail('Expected disabled shell failure.'); }
        catch (RuntimeException $exception) { $this->assertSame('Shell command execution is disabled for this cPanel account. Enable jailed shell access in WHM before retrying.', $exception->getMessage()); }
    }

    public function test_wp_cli_shell_disabled_output_is_rejected_centrally(): void
    {
        $service = new KrystalWordpressProvisioner(new RecordingSshRunner([
            "'option' 'get' 'siteurl'" => ['exit_code' => 0, 'stdout' => '', 'stderr' => 'If you need shell access please contact support'],
        ]), new RecordingCpanelUapiClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shell command execution is disabled');
        $service->verify($this->server(), $this->account(), 'not-returned', 'https://example.test');
    }

    public function test_ssh_probe_checks_all_tools_needed_by_later_steps(): void
    {
        $service = new KrystalWordpressProvisioner(new RecordingSshRunner([
            'for tool in php wp' => ['exit_code' => 1, 'stdout' => '', 'stderr' => 'not returned'],
        ]), new RecordingCpanelUapiClient);
        try { $service->testSsh($this->server(), $this->account(), 'not-returned'); $this->fail('Expected missing tools failure.'); }
        catch (RuntimeException $exception) { $this->assertSame('SSH connected, but PHP or WP-CLI is unavailable for this account.', $exception->getMessage()); }
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
        if (str_contains($command, '__WEBSTAMP_CONNECTED__')) {
            return ['exit_code' => 0, 'stdout' => '__WEBSTAMP_CONNECTED__', 'stderr' => ''];
        }
        if (str_contains($command, '__WEBSTAMP_TOOLS_READY__')) {
            return ['exit_code' => 0, 'stdout' => '__WEBSTAMP_TOOLS_READY__', 'stderr' => ''];
        }
        return ['exit_code' => 0, 'stdout' => '', 'stderr' => ''];
    }
}

class RecordingCpanelUapiClient implements CpanelUapiClient
{
    public array $calls = [];
    public function __construct(private array $responses = []) {}
    public function call(HostingServer $server, HostingAccount $account, string $module, string $function, array $parameters = []): array
    {
        $this->calls[] = compact('module', 'function', 'parameters');
        return $this->responses[$function] ?? ['result' => ['status' => 1, 'data' => null]];
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
