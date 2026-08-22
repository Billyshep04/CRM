<?php

namespace App\Services\Hosting;

use App\Contracts\SshCommandRunner;
use App\Models\HostingAccount;
use App\Models\HostingPackage;
use App\Models\HostingServer;
use App\Models\WordpressProfile;
use RuntimeException;

class KrystalWordpressProvisioner
{
    public function __construct(private readonly SshCommandRunner $ssh) {}

    public function validatePrerequisites(HostingPackage $package, bool $live): array
    {
        if ($live && $package->shell_access === false) {
            throw new RuntimeException('WordPress provisioning cannot continue because Shell Access is not enabled for the selected hosting package.');
        }
        return [
            'shell_access' => $package->shell_access === true ? 'enabled' : 'requested_and_verified_during_setup',
            'ssh_port' => (int) config('hosting.ssh.port', 722),
        ];
    }

    public function testSsh(HostingServer $server, HostingAccount $account, string $password): array
    {
        $this->execute($server, $account, $password, 'printf connected', 'SSH connection failed. Check Shell Access and cPanel credentials.');
        return ['connected' => true, 'port' => (int) config('hosting.ssh.port', 722)];
    }

    public function downloadWordpress(HostingServer $server, HostingAccount $account, string $password): array
    {
        $exists = $this->run($server, $account, $password, 'test -f public_html/wp-load.php');
        if ($exists['exit_code'] === 0) return ['downloaded' => true, 'path' => 'public_html', 'existing_provisioning_download' => true];
        $listing = $this->execute($server, $account, $password, "find public_html -mindepth 1 -maxdepth 1 -printf '%f\\n' 2>/dev/null || true", 'The existing website files could not be inspected.');
        $allowed = ['.htaccess', '.well-known', 'cgi-bin'];
        $unexpected = collect(preg_split('/\R/', trim($listing)) ?: [])->filter()->reject(fn ($name) => in_array($name, $allowed, true))->values();
        if ($unexpected->isNotEmpty()) throw new RuntimeException('WordPress was not installed because public_html contains an existing website.');

        $this->execute($server, $account, $password, $this->wpCliCommand(['core', 'download', '--force']), 'WordPress download failed.', 180);
        return ['downloaded' => true, 'path' => 'public_html'];
    }

    public function createDatabase(HostingServer $server, HostingAccount $account, string $password, string $database): array
    {
        $this->validateDatabaseName($database, $account->username);
        $this->executeUapi($server, $account, $password, ['Mysql', 'create_database', "name={$database}"], 'MySQL database creation failed.');
        return ['database' => $database];
    }

    public function createDatabaseUser(HostingServer $server, HostingAccount $account, string $password, string $username, string $databasePassword): array
    {
        $this->validateDatabaseName($username, $account->username);
        $this->executeUapi($server, $account, $password, ['Mysql', 'create_user', "name={$username}", 'password='.$databasePassword], 'MySQL user creation failed.');
        return ['database_user' => $username];
    }

    public function grantPrivileges(HostingServer $server, HostingAccount $account, string $password, string $database, string $username): array
    {
        $this->executeUapi($server, $account, $password, ['Mysql', 'set_privileges_on_database', "user={$username}", "database={$database}", 'privileges=ALL'], 'Assigning database privileges failed.');
        return ['privileges' => 'ALL'];
    }

    public function createConfig(HostingServer $server, HostingAccount $account, string $password, array $database): array
    {
        $exists = $this->run($server, $account, $password, 'test -f public_html/wp-config.php');
        if ($exists['exit_code'] !== 0) {
            $this->execute($server, $account, $password, $this->wpCliCommand([
                'config', 'create', '--dbname='.$database['name'], '--dbuser='.$database['user'], '--dbpass='.$database['password'], '--dbhost=localhost', '--skip-check',
            ]), 'Creating wp-config.php failed.');
        }
        return ['configured' => true, 'salts' => 'generated'];
    }

    public function install(HostingServer $server, HostingAccount $account, string $password, array $wordpress): array
    {
        $installed = $this->run($server, $account, $password, $this->wpCliCommand(['core', 'is-installed']));
        if ($installed['exit_code'] !== 0) {
            $this->execute($server, $account, $password, $this->wpCliCommand([
                'core', 'install', '--url='.$wordpress['url'], '--title='.$wordpress['title'], '--admin_user='.$wordpress['admin_username'],
                '--admin_password='.$wordpress['admin_password'], '--admin_email='.$wordpress['admin_email'], '--skip-email',
            ]), 'WordPress installation failed.', 180);
        }
        return ['installed' => true, 'url' => $wordpress['url'], 'admin_username' => $wordpress['admin_username']];
    }

    public function configure(HostingServer $server, HostingAccount $account, string $password, ?WordpressProfile $profile, array $options): array
    {
        $configuration = $profile?->configuration ?? [];
        $permalink = $configuration['permalink'] ?? '/%postname%/';
        $timezone = $configuration['timezone'] ?? config('app.timezone', 'Europe/London');
        $this->execute($server, $account, $password, $this->wpCliCommand(['rewrite', 'structure', $permalink, '--hard']), 'Setting WordPress permalinks failed.');
        $this->execute($server, $account, $password, $this->wpCliCommand(['option', 'update', 'timezone_string', $timezone]), 'Setting the WordPress timezone failed.');
        if (! empty($options['site_url'])) {
            $this->execute($server, $account, $password, $this->wpCliCommand(['option', 'update', 'siteurl', $options['site_url']]), 'Setting the WordPress site URL failed.');
            $this->execute($server, $account, $password, $this->wpCliCommand(['option', 'update', 'home', $options['site_url']]), 'Setting the WordPress home URL failed.');
        }
        if (($options['discourage_search_engines'] ?? $configuration['discourage_search_engines'] ?? false) === true) {
            $this->execute($server, $account, $password, $this->wpCliCommand(['option', 'update', 'blog_public', '0']), 'Setting search-engine visibility failed.');
        }
        foreach (($configuration['plugins'] ?? []) as $plugin) {
            if ($plugin === 'webstamp-site-agent') continue;
            if (! preg_match('/^[a-z0-9-]+$/', (string) $plugin)) throw new RuntimeException('The provisioning profile contains an invalid plugin slug.');
            $this->execute($server, $account, $password, $this->wpCliCommand(['plugin', 'install', $plugin, '--activate']), "Installing the {$plugin} plugin failed.", 180);
        }
        if (($configuration['delete_default_content'] ?? false) === true) {
            $this->execute($server, $account, $password, $this->wpCliCommand(['post', 'delete', '1', '--force']), 'Removing default WordPress content failed.');
            $this->execute($server, $account, $password, $this->wpCliCommand(['comment', 'delete', '--all', '--force']), 'Removing default WordPress comments failed.');
        }
        return ['configured' => true, 'profile' => $profile?->slug, 'agent_installation' => 'separate_step'];
    }

    public function verify(HostingServer $server, HostingAccount $account, string $password, string $expectedUrl): array
    {
        $siteUrl = trim($this->execute($server, $account, $password, $this->wpCliCommand(['option', 'get', 'siteurl']), 'WordPress verification failed.'));
        if (rtrim($siteUrl, '/') !== rtrim($expectedUrl, '/')) throw new RuntimeException('WordPress verification failed because the installed site URL does not match.');
        $this->execute($server, $account, $password, 'test -f public_html/wp-config.php', 'WordPress verification failed because wp-config.php is missing.');
        $tables = trim($this->execute($server, $account, $password, $this->wpCliCommand(['db', 'tables']), 'WordPress verification failed because its database tables are unavailable.'));
        if ($tables === '') throw new RuntimeException('WordPress verification failed because its database tables are unavailable.');
        return ['verified' => true, 'site_url' => $siteUrl];
    }

    public function wpCliCommand(array $arguments): string
    {
        $safe = collect($arguments)->map(fn ($argument) => escapeshellarg((string) $argument))->implode(' ');
        return 'cd ~/public_html && php -d disable_functions= "$(which wp)" '.$safe;
    }

    private function executeUapi(HostingServer $server, HostingAccount $account, string $password, array $arguments, string $safeFailure): void
    {
        $command = 'uapi --output=json '.collect($arguments)->map(fn ($argument) => escapeshellarg((string) $argument))->implode(' ');
        $output = $this->execute($server, $account, $password, $command, $safeFailure);
        $payload = json_decode($output, true);
        $status = data_get($payload, 'result.status');
        if ($status !== 1 && ! str_contains(strtolower($output), 'already exists')) throw new RuntimeException($safeFailure);
    }

    private function execute(HostingServer $server, HostingAccount $account, string $password, string $command, string $safeFailure, int $timeout = 60): string
    {
        $result = $this->run($server, $account, $password, $command, $timeout);
        if ($result['exit_code'] !== 0) throw new RuntimeException($safeFailure);
        return $result['output'];
    }

    private function run(HostingServer $server, HostingAccount $account, string $password, string $command, int $timeout = 60): array
    {
        return $this->ssh->run($server, $account, $password, $command, $timeout);
    }

    private function validateDatabaseName(string $name, string $cpanelUsername): void
    {
        if (! preg_match('/^[a-z0-9_]+$/', $name) || ! str_starts_with($name, $cpanelUsername.'_')) throw new RuntimeException('The generated database name is invalid.');
    }
}
