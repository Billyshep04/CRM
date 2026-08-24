<?php

namespace App\Services\Hosting;

use App\Contracts\SshCommandRunner;
use App\Contracts\CpanelUapiClient;
use App\Models\HostingAccount;
use App\Models\HostingPackage;
use App\Models\HostingServer;
use App\Models\WordpressProfile;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KrystalWordpressProvisioner
{
    public function __construct(private readonly SshCommandRunner $ssh, private readonly CpanelUapiClient $uapi) {}

    public function validatePrerequisites(HostingPackage $package, bool $live, ?HostingServer $server = null): array
    {
        if ($live && $package->shell_access === false) {
            throw new RuntimeException('WordPress provisioning cannot continue because Shell Access is not enabled for the selected hosting package.');
        }
        $fingerprint = trim((string) ($server?->metadata['ssh_host_fingerprint'] ?? config('hosting.ssh.host_fingerprint')));
        if ($live && (! class_exists(SSH2::class) || ! class_exists(PublicKeyLoader::class))) {
            throw new RuntimeException('The secure SSH library is not installed on the CRM server. Run Composer install before provisioning.');
        }
        if ($live && $fingerprint === '') {
            throw new RuntimeException('SSH host verification is not configured. Add the Krystal SSH host fingerprint in Websites → Krystal connection settings before provisioning.');
        }
        if ($live && ! preg_match('/^(?:sha256:)?[a-z0-9+\/:]{32,}={0,2}$/i', $fingerprint)) {
            throw new RuntimeException('The saved Krystal SSH host fingerprint is invalid. Save a verified SHA256 fingerprint before provisioning.');
        }
        return [
            'shell_access' => $package->shell_access === true ? 'enabled' : 'requested_and_verified_during_setup',
            'ssh_host_verification' => $live ? 'configured' : 'preview',
            'ssh_port' => (int) config('hosting.ssh.port', 722),
        ];
    }

    public function testSsh(HostingServer $server, HostingAccount $account, string $password): array
    {
        $this->execute($server, $account, $password, 'printf connected', 'SSH connection failed. Check Shell Access and cPanel credentials.');
        $this->execute(
            $server,
            $account,
            $password,
            'for tool in php wp; do command -v "$tool" >/dev/null 2>&1 || exit 1; done; printf tools-ready',
            'SSH connected, but PHP or WP-CLI is unavailable for this account.'
        );
        return ['connected' => true, 'tools_verified' => ['php', 'wp'], 'port' => (int) config('hosting.ssh.port', 722)];
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

    public function databaseNames(HostingServer $server, HostingAccount $account, string $password): array
    {
        $payload = $this->executeUapi($server, $account, $password, ['Mysql', 'get_restrictions'], 'The cPanel MySQL naming rules could not be read.');
        $restrictions = data_get($payload, 'result.data');
        if (! is_array($restrictions)) {
            throw new RuntimeException('The cPanel MySQL naming rules could not be read.');
        }

        if (! array_key_exists('prefix', $restrictions)) {
            throw new RuntimeException('The cPanel MySQL naming rules did not include the required database prefix.');
        }

        $prefix = (string) $restrictions['prefix'];
        $databaseSuffix = 'wp';
        $userSuffix = 'wpuser';
        $database = $prefix.$databaseSuffix;
        $username = $prefix.$userSuffix;
        $databaseLimit = (int) ($restrictions['max_database_name_length'] ?? 64);
        $usernameLimit = (int) ($restrictions['max_username_length'] ?? 32);
        if ($database === '' || strlen($database) > $databaseLimit || strlen($username) > $usernameLimit) {
            throw new RuntimeException('The cPanel MySQL prefix leaves insufficient room for the WordPress database names.');
        }

        return [
            'database' => $database,
            'database_user' => $username,
            'database_api_name' => $databaseSuffix,
            'database_user_api_name' => $userSuffix,
        ];
    }

    public function createDatabase(HostingServer $server, HostingAccount $account, string $password, string $database, ?string $apiName = null): array
    {
        $this->validateDatabaseName($database);
        $this->executeUapi($server, $account, $password, ['Mysql', 'create_database', 'name='.$database], 'MySQL database creation failed.');
        return ['database' => $database];
    }

    public function createDatabaseUser(HostingServer $server, HostingAccount $account, string $password, string $username, string $databasePassword, ?string $apiName = null): array
    {
        $this->validateDatabaseName($username);
        $this->executeUapi($server, $account, $password, ['Mysql', 'create_user', 'name='.$username, 'password='.$databasePassword], 'MySQL user creation failed.');
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
        $diagnostic = $this->verificationDiagnostic($server, $account, $password);
        Log::info('Temporary WordPress verification diagnostic.', $diagnostic);
        if (rtrim($siteUrl, '/') !== rtrim($expectedUrl, '/')) {
            $actualSiteUrl = $this->safeDiagnosticText($diagnostic['siteurl'] ?: $siteUrl, [
                $password,
                ...array_values(array_filter((array) ($server->credentials ?? []), 'is_scalar')),
            ], 500);
            throw new RuntimeException(
                'WordPress verification failed because the installed site URL does not match. '
                .'Expected "'.$expectedUrl.'" but found "'.$actualSiteUrl.'".'
            );
        }
        $this->execute($server, $account, $password, 'test -f public_html/wp-config.php', 'WordPress verification failed because wp-config.php is missing.');
        $tables = trim($this->execute($server, $account, $password, $this->wpCliCommand(['db', 'tables']), 'WordPress verification failed because its database tables are unavailable.'));
        if ($tables === '') throw new RuntimeException('WordPress verification failed because its database tables are unavailable.');
        return ['verified' => true, 'site_url' => $siteUrl];
    }

    private function verificationDiagnostic(HostingServer $server, HostingAccount $account, string $password): array
    {
        $siteUrlCommand = str_replace('cd ~/public_html && ', '', $this->wpCliCommand(['option', 'get', 'siteurl']));
        $homeCommand = str_replace('cd ~/public_html && ', '', $this->wpCliCommand(['option', 'get', 'home']));
        $command = <<<'SH'
tmp=$(mktemp) || exit 1; trap 'rm -f "$tmp"' EXIT; ssh_user=''; working_directory=''; siteurl=''; home=''; { if cd ~/public_html; then ssh_user=$(whoami); working_directory=$(pwd -P); siteurl=$(__SITEURL__); home=$(__HOME__); fi; } 2>"$tmp"; printf '__WS_SSH_USER_BEGIN__\n%s\n__WS_SSH_USER_END__\n' "$ssh_user"; printf '__WS_WORKING_DIRECTORY_BEGIN__\n%s\n__WS_WORKING_DIRECTORY_END__\n' "$working_directory"; printf '__WS_SITEURL_BEGIN__\n%s\n__WS_SITEURL_END__\n' "$siteurl"; printf '__WS_HOME_BEGIN__\n%s\n__WS_HOME_END__\n' "$home"; printf '__WS_STDERR_BEGIN__\n'; cat "$tmp"; printf '\n__WS_STDERR_END__\n'
SH;
        $command = str_replace(['__SITEURL__', '__HOME__'], [$siteUrlCommand, $homeCommand], $command);
        $result = $this->run($server, $account, $password, $command);
        $values = collect([
            'ssh_user' => 'SSH_USER',
            'working_directory' => 'WORKING_DIRECTORY',
            'siteurl' => 'SITEURL',
            'home' => 'HOME',
            'stderr_summary' => 'STDERR',
        ])->mapWithKeys(function (string $marker, string $field) use ($result) {
            preg_match('/^__WS_'.preg_quote($marker, '/').'_BEGIN__\R(.*?)\R__WS_'.preg_quote($marker, '/').'_END__$/ms', (string) ($result['output'] ?? ''), $matches);
            return [$field => trim((string) ($matches[1] ?? ''))];
        })->all();

        $secrets = collect([
            $password,
            ...array_values(array_filter((array) ($server->credentials ?? []), 'is_scalar')),
        ])->map(fn ($value) => (string) $value)->filter()->values()->all();
        $values['stderr_summary'] = $this->safeDiagnosticText($values['stderr_summary'], $secrets, 300);

        return $values;
    }

    private function safeDiagnosticText(string $value, array $secrets, int $limit): string
    {
        $value = trim(strip_tags($value));
        foreach ($secrets as $secret) {
            if ($secret !== '') $value = str_replace($secret, '[REDACTED]', $value);
        }
        $value = preg_replace('/\b(password|token|authorization)\s*[:=]\s*\S+/i', '$1=[REDACTED]', $value) ?? '';
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, $limit);
    }

    public function wpCliCommand(array $arguments): string
    {
        $safe = collect($arguments)->map(fn ($argument) => $this->shellArgument((string) $argument))->implode(' ');
        return 'cd ~/public_html && php -d disable_functions= "$(which wp)" '.$safe;
    }

    private function executeUapi(HostingServer $server, HostingAccount $account, string $password, array $arguments, string $safeFailure): array
    {
        [$module, $function] = $arguments;
        $parameters = collect(array_slice($arguments, 2))->mapWithKeys(function ($argument) {
            [$key, $value] = array_pad(explode('=', (string) $argument, 2), 2, '');
            return [$key => $value];
        })->all();
        $payload = $this->uapi->call($server, $account, (string) $module, (string) $function, $parameters);
        $output = json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '';
        $status = data_get($payload, 'result.status');
        if ($status !== 1 && ! str_contains(strtolower($output), 'already exists')) {
            $errors = collect((array) data_get($payload, 'result.errors', []))->filter()->map(fn ($error) => trim(strip_tags((string) $error)))->values()->all();
            Log::warning('cPanel UAPI provisioning step failed.', [
                'hosting_account_id' => $account->id,
                'module' => $module,
                'function' => $function,
                'errors' => $this->redactUapiErrors($errors, $arguments),
            ]);
            throw new RuntimeException($this->safeUapiFailure($errors, $safeFailure));
        }

        return is_array($payload) ? $payload : [];
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

    private function validateDatabaseName(string $name): void
    {
        if (! preg_match('/^[a-z0-9_]+$/', $name)) throw new RuntimeException('The generated database name is invalid.');
    }

    private function shellArgument(string $value): string
    {
        if (str_contains($value, "\0")) {
            throw new RuntimeException('A provisioning command contains an invalid value.');
        }

        // POSIX single-quote encoding without PHP's escapeshellarg(), which is
        // disabled by some shared-hosting PHP configurations.
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }

    private function safeUapiFailure(array $errors, string $fallback): string
    {
        $message = strtolower(implode(' ', $errors));
        return match (true) {
            str_contains($message, 'quota'), str_contains($message, 'maximum number') => 'The cPanel account has reached its MySQL database or user quota.',
            str_contains($message, 'disabled'), str_contains($message, 'role') => 'MySQL database management is not enabled for this cPanel account.',
            str_contains($message, 'length'), str_contains($message, 'invalid') => 'cPanel rejected the generated MySQL name because it does not meet the server naming rules.',
            str_contains($message, 'permission'), str_contains($message, 'privilege') => 'The cPanel account does not have permission to manage MySQL databases.',
            default => $fallback,
        };
    }

    private function redactUapiErrors(array $errors, array $arguments): array
    {
        $secrets = collect($arguments)
            ->filter(fn ($argument) => str_starts_with((string) $argument, 'password='))
            ->map(fn ($argument) => substr((string) $argument, strlen('password=')))
            ->filter();

        return collect($errors)->map(function (string $error) use ($secrets) {
            foreach ($secrets as $secret) $error = str_replace($secret, '[REDACTED]', $error);
            return mb_substr($error, 0, 500);
        })->all();
    }
}
