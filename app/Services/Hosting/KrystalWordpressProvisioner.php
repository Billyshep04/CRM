<?php

namespace App\Services\Hosting;

use App\Contracts\SshCommandRunner;
use App\Contracts\CpanelUapiClient;
use App\Models\HostingAccount;
use App\Models\HostingPackage;
use App\Models\HostingServer;
use App\Models\WordpressProfile;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use RuntimeException;

class KrystalWordpressProvisioner
{
    public function __construct(private readonly SshCommandRunner $ssh, private readonly CpanelUapiClient $uapi) {}

    public function validatePrerequisites(HostingPackage $package, bool $live, ?HostingServer $server = null): array
    {
        if ($live && $package->shell_access === false) {
            throw new RuntimeException('WordPress provisioning cannot continue because usable SSH access is not enabled for the selected hosting package.');
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
        $connected = trim($this->execute($server, $account, $password, "printf '__WEBSTAMP_CONNECTED__'", 'SSH connection failed. Check usable SSH access and cPanel credentials.'));
        if ($connected !== '__WEBSTAMP_CONNECTED__') {
            throw new RuntimeException('SSH connection failed because the account could not execute shell commands. Check usable SSH access in WHM.');
        }
        $tools = trim($this->execute(
            $server,
            $account,
            $password,
            'for tool in php wp; do command -v "$tool" >/dev/null 2>&1 || exit 1; done; printf \'__WEBSTAMP_TOOLS_READY__\'',
            'SSH connected, but PHP or WP-CLI is unavailable for this account.'
        ));
        if ($tools !== '__WEBSTAMP_TOOLS_READY__') {
            throw new RuntimeException('SSH connected, but PHP or WP-CLI could not be executed for this account.');
        }
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
        } else {
            $configuredDatabase = trim($this->execute($server, $account, $password, $this->wpCliCommand(['config', 'get', 'DB_NAME']), 'The existing wp-config.php could not be verified.'));
            $configuredUser = trim($this->execute($server, $account, $password, $this->wpCliCommand(['config', 'get', 'DB_USER']), 'The existing wp-config.php could not be verified.'));
            if (! hash_equals((string) $database['name'], $configuredDatabase) || ! hash_equals((string) $database['user'], $configuredUser)) {
                throw new RuntimeException('The existing wp-config.php belongs to different database credentials. Provisioning stopped without overwriting it.');
            }
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
        if (! empty($options['wordpress_environment_type'])) {
            $environment = (string) $options['wordpress_environment_type'];
            if (! in_array($environment, ['local', 'development', 'staging', 'production'], true)) throw new RuntimeException('The WordPress environment type is invalid.');
            $this->execute($server, $account, $password, $this->wpCliCommand(['config', 'set', 'WP_ENVIRONMENT_TYPE', $environment, '--type=constant']), 'Setting the WordPress environment marker failed.');
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
        if (rtrim($siteUrl, '/') !== rtrim($expectedUrl, '/')) {
            throw new RuntimeException('WordPress verification failed because the installed site URL does not match.');
        }
        $home = trim($this->execute($server, $account, $password, $this->wpCliCommand(['option', 'get', 'home']), 'WordPress verification failed.'));
        if (rtrim($home, '/') !== rtrim($expectedUrl, '/')) {
            throw new RuntimeException('WordPress verification failed because the installed home URL does not match.');
        }
        $this->execute($server, $account, $password, 'test -f public_html/wp-config.php', 'WordPress verification failed because wp-config.php is missing.');
        $tables = trim($this->execute($server, $account, $password, $this->wpCliCommand(['db', 'tables']), 'WordPress verification failed because its database tables are unavailable.'));
        if ($tables === '') throw new RuntimeException('WordPress verification failed because its database tables are unavailable.');
        return ['verified' => true, 'site_url' => $siteUrl, 'home_url' => $home];
    }

    public function resetAdminPassword(HostingServer $server, HostingAccount $account, string $password, string $username, string $newPassword): array
    {
        $this->execute(
            $server,
            $account,
            $password,
            $this->wpCliCommand(['user', 'update', $username, '--user_pass='.$newPassword]),
            'The WordPress login could not be reset.'
        );

        return ['reset' => true, 'username' => $username];
    }

    public function migrateDomain(HostingServer $server, HostingAccount $account, string $password, string $fromUrl, string $toUrl, bool $allowIndexing): array
    {
        $siteUrl = rtrim(trim($this->execute($server, $account, $password, $this->wpCliCommand(['option', 'get', 'siteurl']), 'The current WordPress URL could not be read.')), '/');
        $home = rtrim(trim($this->execute($server, $account, $password, $this->wpCliCommand(['option', 'get', 'home']), 'The current WordPress home URL could not be read.')), '/');
        $fromUrl = rtrim($fromUrl, '/');
        $toUrl = rtrim($toUrl, '/');

        if ($siteUrl !== $toUrl || $home !== $toUrl) {
            if ($siteUrl !== $fromUrl || $home !== $fromUrl) {
                throw new RuntimeException('WordPress is using an unexpected URL. Launch stopped before changing the database.');
            }
            $arguments = ['search-replace', $fromUrl, $toUrl, '--all-tables-with-prefix', '--skip-columns=guid', '--precise'];
            $this->execute($server, $account, $password, $this->wpCliCommand([...$arguments, '--dry-run']), 'The WordPress domain migration dry run failed.', 180);
            $this->execute($server, $account, $password, $this->wpCliCommand($arguments), 'The WordPress domain migration failed.', 300);
            $this->execute($server, $account, $password, $this->wpCliCommand(['option', 'update', 'siteurl', $toUrl]), 'Updating the WordPress site URL failed.');
            $this->execute($server, $account, $password, $this->wpCliCommand(['option', 'update', 'home', $toUrl]), 'Updating the WordPress home URL failed.');
        }

        $this->execute($server, $account, $password, $this->wpCliCommand(['config', 'set', 'WP_ENVIRONMENT_TYPE', 'production', '--type=constant']), 'Updating the WordPress environment marker failed.');
        if ($allowIndexing) {
            $this->execute($server, $account, $password, $this->wpCliCommand(['option', 'update', 'blog_public', '1']), 'Enabling search-engine visibility failed.');
        }
        $this->execute($server, $account, $password, $this->wpCliCommand(['rewrite', 'flush', '--hard']), 'Refreshing WordPress permalinks failed.');

        return ['migrated' => true, 'from_url' => $fromUrl, 'to_url' => $toUrl, 'indexing_enabled' => $allowIndexing];
    }

    public function ensureMonitoringAgent(HostingServer $server, HostingAccount $account, string $password, string $agentToken): array
    {
        if ($agentToken === '') {
            throw new RuntimeException('The monitoring connection does not have a valid CRM identity token.');
        }

        $plugin = $this->run($server, $account, $password, $this->wpCliCommand(['plugin', 'is-installed', 'webstamp-site-agent']));
        $installed = $plugin['exit_code'] === 0;
        if (! $installed) {
            $source = file_get_contents(base_path('wordpress-plugin/webstamp-site-agent/webstamp-site-agent.php'));
            if (! is_string($source) || $source === '') {
                throw new RuntimeException('The WebStamp monitoring plugin package is unavailable on the CRM server.');
            }

            $encoded = base64_encode($source);
            $script = '$dir=getcwd()."/wp-content/plugins/webstamp-site-agent";'
                .'if(!is_dir($dir)&&!mkdir($dir,0755,true)){exit(1);}'
                .'$source=base64_decode("'.$encoded.'",true);'
                .'if($source===false||file_put_contents($dir."/webstamp-site-agent.php",$source,LOCK_EX)===false){exit(1);}'
                .'echo "__WEBSTAMP_AGENT_INSTALLED__";';
            $output = trim($this->execute(
                $server,
                $account,
                $password,
                'cd ~/public_html && php -r '.$this->shellArgument($script),
                'The WebStamp monitoring plugin could not be installed.'
            ));
            if ($output !== '__WEBSTAMP_AGENT_INSTALLED__') {
                throw new RuntimeException('The WebStamp monitoring plugin installation could not be verified.');
            }
        }

        $this->execute($server, $account, $password, $this->wpCliCommand(['plugin', 'activate', 'webstamp-site-agent']), 'The WebStamp monitoring plugin could not be activated.');
        $this->execute($server, $account, $password, $this->wpCliCommand(['option', 'update', 'webstamp_agent_token', $agentToken]), 'The WebStamp monitoring identity could not be configured.');
        $this->execute($server, $account, $password, $this->wpCliCommand(['rewrite', 'flush', '--hard']), 'The WebStamp monitoring endpoint could not be enabled.');
        $this->execute($server, $account, $password, $this->wpCliCommand(['plugin', 'is-active', 'webstamp-site-agent']), 'The WebStamp monitoring plugin is not active.');

        return ['installed' => true, 'new_installation' => ! $installed, 'active' => true, 'identity' => 'retained'];
    }

    public function redirectDevelopmentDomain(HostingServer $server, HostingAccount $account, string $password, string $developmentDomain, string $productionDomain): array
    {
        $developmentDomain = strtolower(rtrim(trim($developmentDomain), '.'));
        $productionDomain = strtolower(rtrim(trim($productionDomain), '.'));
        foreach ([$developmentDomain, $productionDomain] as $domain) {
            if (! preg_match('/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/', $domain)) {
                throw new RuntimeException('The development redirect contains an invalid domain.');
            }
        }

        $escapedDevelopment = preg_quote($developmentDomain, '/');
        $block = "# BEGIN WebStamp Development Redirect\n"
            ."RewriteEngine On\n"
            ."RewriteCond %{HTTP_HOST} ^{$escapedDevelopment}$ [NC]\n"
            ."RewriteRule ^ https://{$productionDomain}%{REQUEST_URI} [R=301,L,NE]\n"
            ."# END WebStamp Development Redirect\n";
        $encoded = base64_encode($block);
        $script = '$path=getcwd()."/.htaccess";'
            .'$contents=is_file($path)?file_get_contents($path):"";'
            .'$contents=preg_replace("/# BEGIN WebStamp Development Redirect.*?# END WebStamp Development Redirect\\R?/s","",$contents);'
            .'$block=base64_decode("'.$encoded.'",true);'
            .'if($block===false||file_put_contents($path,$block.$contents)===false){exit(1);}'
            .'echo "__WEBSTAMP_REDIRECT_READY__";';
        $output = trim($this->execute(
            $server,
            $account,
            $password,
            'cd ~/public_html && php -r '.$this->shellArgument($script),
            'The development-domain redirect could not be configured.'
        ));
        if ($output !== '__WEBSTAMP_REDIRECT_READY__') {
            throw new RuntimeException('The development-domain redirect could not be verified.');
        }

        return ['redirected' => true, 'from_domain' => $developmentDomain, 'to_domain' => $productionDomain];
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
        return $result['stdout'];
    }

    private function run(HostingServer $server, HostingAccount $account, string $password, string $command, int $timeout = 60): array
    {
        $result = $this->ssh->run($server, $account, $password, $command, $timeout);
        $stdout = (string) ($result['stdout'] ?? '');
        $stderr = (string) ($result['stderr'] ?? '');
        $combined = strtolower($stdout."\n".$stderr);
        if (str_contains($combined, 'shell access is not enabled on your account')
            || str_contains($combined, 'if you need shell access please contact support')
            || str_contains($combined, '/usr/local/cpanel/bin/noshell')) {
            throw new RuntimeException('Shell command execution is disabled for this cPanel account. Enable usable SSH access in WHM before retrying.');
        }

        return [
            'exit_code' => (int) ($result['exit_code'] ?? 1),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
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
