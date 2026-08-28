<?php

namespace App\Services\Hosting;

use App\Contracts\HostingProviderInterface;
use App\Models\HostingAccount;
use App\Models\HostingServer;
use App\Models\Website;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class KrystalWhmProvider implements HostingProviderInterface
{
    private const JAILSHELL = '/usr/local/cpanel/bin/jailshell';

    private function client(HostingServer $server, int $timeout = 20): PendingRequest
    {
        $credentials = $server->credentials ?? [];

        if (! $server->hostname || empty($credentials['username']) || empty($credentials['token'])) {
            throw new RuntimeException('WHM credentials are not configured.');
        }

        return Http::withHeaders([
            'Authorization' => 'whm '.$credentials['username'].':'.$credentials['token'],
        ])->acceptJson()
            ->connectTimeout(8)
            ->timeout($timeout)
            ->baseUrl('https://'.preg_replace('/:\d+$/', '', $server->hostname).':2087/json-api');
    }

    private function call(HostingServer $server, string $function, array $query = [], int $timeout = 20): array
    {
        try {
            $response = $this->client($server, $timeout)->get('/'.$function, [
                'api.version' => 1,
                ...$query,
            ]);
        } catch (ConnectionException) {
            if ($function === 'createacct') {
                throw new RuntimeException(
                    'WHM did not return the account-creation result in time. The CRM will check whether the account was created before retrying.'
                );
            }
            throw new RuntimeException(
                'The CRM could not connect to WHM on port 2087. Check the WHM hostname and that outbound HTTPS connections to port 2087 are allowed.'
            );
        }

        $payload = $response->json();

        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException(
                'WHM rejected the reseller username or API token, or the token does not have permission for this action.'
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->failedResponseMessage($response, $payload));
        }

        if (! is_array($payload)) {
            throw new RuntimeException(
                'WHM returned an unexpected response. Check that the hostname is the server hostname, not a cPanel account domain.'
            );
        }

        if ((string) data_get($payload, 'metadata.result') === '0') {
            throw new RuntimeException($this->safeWhmReason($payload)
                ?: 'WHM refused the request. Check the reseller account and API token permissions.');
        }

        return $payload;
    }

    private function failedResponseMessage(Response $response, mixed $payload): string
    {
        $reason = is_array($payload) ? $this->safeWhmReason($payload) : null;

        return $reason
            ? "WHM returned HTTP {$response->status()}: {$reason}"
            : "WHM returned HTTP {$response->status()}. Check the server hostname, reseller API token, and token permissions.";
    }

    private function safeWhmReason(array $payload): ?string
    {
        $reason = data_get($payload, 'metadata.reason')
            ?? data_get($payload, 'metadata.error')
            ?? data_get($payload, 'metadata.errors')
            ?? data_get($payload, 'data.reason')
            ?? data_get($payload, 'message');

        if (is_array($reason)) {
            $reason = collect($reason)->flatten()->filter(fn ($value) => is_scalar($value))->implode(' ');
        }
        if (! is_string($reason) || trim($reason) === '') {
            return null;
        }

        return Str::limit(trim(strip_tags($reason)), 240);
    }

    public function testConnection(HostingServer $server): array
    {
        $this->call($server, 'listaccts');

        return ['ok' => true, 'message' => 'Krystal WHM connected.'];
    }

    public function accounts(HostingServer $server): array
    {
        return collect($this->call($server, 'listaccts')['data']['acct'] ?? [])->map(fn ($account) => [
            'external_id' => (string) $account['user'],
            'username' => (string) $account['user'],
            'primary_domain' => $account['domain'] ?? null,
            'assigned_ip' => $account['ip'] ?? null,
            'package_name' => $account['plan'] ?? null,
            'status' => ($account['suspended'] ?? 0) ? 'suspended' : 'active',
            'disk_used_bytes' => $this->bytes($account['diskused'] ?? null),
            'disk_limit_bytes' => $this->bytes($account['disklimit'] ?? null),
            'metadata' => ['owner' => $account['owner'] ?? null, 'email' => $account['email'] ?? null],
        ])->all();
    }

    public function packages(HostingServer $server): array
    {
        return collect($this->call($server, 'listpkgs')['data']['pkg'] ?? [])->map(fn ($package) => [
            'external_id' => (string) $package['name'],
            'name' => (string) $package['name'],
            'limits' => $package,
            'shell_access' => $this->shellAccess($package),
        ])->all();
    }

    public function domains(HostingServer $server, HostingAccount $account): array
    {
        $response = $this->call($server, 'cpanel', [
            'cpanel_jsonapi_user' => strtolower($account->username),
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => 'DomainInfo',
            'cpanel_jsonapi_func' => 'list_domains',
            'hide_temporary_domains' => 1,
        ]);

        $data = data_get($response, 'data.result.data')
            ?? data_get($response, 'data.uapi.result.data')
            ?? data_get($response, 'data.data')
            ?? [];

        if (! is_array($data)) {
            return $account->primary_domain
                ? [['domain' => $account->primary_domain, 'type' => 'primary']]
                : [];
        }

        $domains = [];
        if (! empty($data['main_domain'])) {
            $domains[] = ['domain' => $data['main_domain'], 'type' => 'primary'];
        }
        foreach (($data['addon_domains'] ?? []) as $domain) {
            $domains[] = ['domain' => $domain, 'type' => 'addon'];
        }

        if ($domains === [] && $account->primary_domain) {
            $domains[] = ['domain' => $account->primary_domain, 'type' => 'primary'];
        }

        return collect($domains)
            ->filter(fn ($item) => is_string($item['domain'] ?? null) && trim($item['domain']) !== '')
            ->map(fn ($item) => [
                'domain' => strtolower(rtrim(trim($item['domain']), '.')),
                'type' => $item['type'],
            ])
            ->unique('domain')
            ->values()
            ->all();
    }

    public function createAccount(HostingServer $server, array $data): array
    {
        $domain = strtolower(rtrim((string) $data['domain'], '.'));
        $proposedUsername = strtolower((string) $data['username']);
        $existing = $this->exactDomainAccounts($server, $domain);
        if ($existing !== []) {
            $match = $this->singleDomainAccount($existing, $domain);
            if (($data['retrying'] ?? false) === true && strtolower($match['username']) === $proposedUsername) {
                Log::info('WHM provisioning retry reconciled an existing account.', [
                    'domain' => $domain,
                    'authoritative_username' => $match['username'],
                ]);

                return [
                    ...$match,
                    'package_name' => $match['package_name'] ?? $data['package_name'],
                    'metadata' => [...($match['metadata'] ?? []), 'reconciled_provisioning_retry' => true],
                ];
            }

            Log::warning('WHM domain reconciliation mismatch.', [
                'domain' => $domain,
                'proposed_username' => $proposedUsername,
                'authoritative_username' => $match['username'],
            ]);
            throw new RuntimeException(
                "This domain already exists on Krystal under cPanel account \"{$match['username']}\". Link the existing hosting account explicitly or use another domain."
            );
        }

        $query = [
            'username' => $data['username'],
            'domain' => $data['domain'],
            'password' => $data['password'],
            'plan' => $data['package_name'],
        ];
        try {
            $response = $this->call(
                $server,
                'createacct',
                $query,
                max(30, (int) config('hosting.whm_account_creation_timeout_seconds', 120))
            );
        } catch (RuntimeException $exception) {
            $safe = $this->redactCreateAccountSecrets($exception->getMessage(), $server, $data);
            Log::warning('WHM createacct failed.', [
                'domain' => $domain,
                'proposed_username' => $proposedUsername,
                'reason' => $safe,
            ]);
            throw new RuntimeException($safe, 0, $exception);
        }

        if (data_get($response, 'metadata.result') !== 1) {
            $safe = $this->redactCreateAccountSecrets(
                $this->safeWhmReason($response) ?: 'WHM returned an invalid account-creation response.',
                $server,
                $data
            );
            Log::warning('WHM createacct failed.', [
                'domain' => $domain,
                'proposed_username' => $proposedUsername,
                'reason' => $safe,
                'metadata_result_type' => get_debug_type(data_get($response, 'metadata.result')),
            ]);
            throw new RuntimeException($safe);
        }

        Log::info('WHM createacct succeeded.', [
            'domain' => $domain,
            'proposed_username' => $proposedUsername,
            'metadata_result' => 1,
        ]);

        $authoritative = $this->singleDomainAccount($this->exactDomainAccounts($server, $domain), $domain);
        Log::info('WHM authoritative account discovered.', [
            'domain' => $domain,
            'proposed_username' => $proposedUsername,
            'authoritative_username' => $authoritative['username'],
            'requested_package' => $data['package_name'],
            'assigned_package' => $authoritative['package_name'] ?? null,
        ]);

        return [
            ...$authoritative,
            'package_name' => $authoritative['package_name'] ?? $data['package_name'],
            'metadata' => [
                ...($authoritative['metadata'] ?? []),
                'whm_message' => $this->redactCreateAccountSecrets(
                    (string) ($response['metadata']['reason'] ?? 'Created'),
                    $server,
                    $data
                ),
                'provisioned_by_crm' => true,
            ],
        ];
    }

    public function verifyAccount(HostingServer $server, HostingAccount $account): array
    {
        $expectedDomain = strtolower(rtrim((string) $account->primary_domain, '.'));
        $matches = $this->exactDomainAccounts($server, $expectedDomain);
        if ($matches === []) throw new RuntimeException('The new cPanel account is not visible in WHM yet. Retry this step shortly.');
        $match = $this->singleDomainAccount($matches, $expectedDomain);
        $actualDomain = strtolower(rtrim((string) $match['primary_domain'], '.'));
        if (($match['status'] ?? 'active') !== 'active') {
            throw new RuntimeException('The cPanel account exists in WHM but is not active.');
        }
        if (empty($match['assigned_ip'])) {
            throw new RuntimeException('The cPanel account exists in WHM but does not yet have an assigned IP address.');
        }

        return [
            'ready' => true,
            'external_id' => $match['external_id'],
            'username' => $match['username'],
            'status' => 'active',
            'assigned_ip' => $match['assigned_ip'],
            'primary_domain' => $actualDomain,
            'username_matches_stored' => strtolower($match['username']) === strtolower($account->username),
        ];
    }

    public function ensureJailedShell(HostingServer $server, HostingAccount $account): array
    {
        try {
            $shell = $this->accountShell($server, $account);
        } catch (RuntimeException) {
            Log::warning('WHM jailed shell state could not be inspected.', [
                'hosting_account_id' => $account->id,
                'username' => $account->username,
            ]);
            throw new RuntimeException($this->shellAccessFailure($account));
        }
        if ($shell === self::JAILSHELL) {
            return ['shell' => $shell, 'changed' => false];
        }

        Log::info('WHM jailed shell assignment requested.', [
            'hosting_account_id' => $account->id,
            'username' => $account->username,
            'current_shell' => $shell ?: 'unknown',
        ]);

        try {
            $this->call($server, 'modifyacct', [
                'user' => $account->username,
                'HASSHELL' => 1,
                'shell' => self::JAILSHELL,
            ]);
        } catch (RuntimeException) {
            Log::warning('WHM jailed shell assignment denied.', [
                'hosting_account_id' => $account->id,
                'username' => $account->username,
                'reason' => 'whm_permission_or_api_failure',
            ]);
            throw new RuntimeException($this->shellAccessFailure($account));
        }

        try {
            $confirmed = $this->accountShell($server, $account);
        } catch (RuntimeException) {
            Log::warning('WHM jailed shell assignment could not be verified.', [
                'hosting_account_id' => $account->id,
                'username' => $account->username,
            ]);
            throw new RuntimeException($this->shellAccessFailure($account));
        }
        if ($confirmed !== self::JAILSHELL) {
            Log::warning('WHM jailed shell assignment was not confirmed.', [
                'hosting_account_id' => $account->id,
                'username' => $account->username,
                'reported_shell' => $confirmed ?: 'unknown',
            ]);
            throw new RuntimeException($this->shellAccessFailure($account));
        }

        Log::info('WHM jailed shell assignment confirmed.', [
            'hosting_account_id' => $account->id,
            'username' => $account->username,
            'shell' => self::JAILSHELL,
        ]);

        return ['shell' => $confirmed, 'changed' => true];
    }

    private function accountShell(HostingServer $server, HostingAccount $account): string
    {
        $payload = $this->call($server, 'accountsummary', ['user' => $account->username]);
        $accounts = data_get($payload, 'data.acct');
        if (! is_array($accounts) || count($accounts) !== 1 || ! is_array($accounts[0])) {
            throw new RuntimeException('WHM returned an invalid account summary while checking jailed shell access.');
        }

        $username = strtolower(trim((string) ($accounts[0]['user'] ?? '')));
        if ($username !== strtolower($account->username)) {
            throw new RuntimeException('WHM returned the wrong account while checking jailed shell access.');
        }

        return trim((string) ($accounts[0]['shell'] ?? ''));
    }

    private function shellAccessFailure(HostingAccount $account): string
    {
        return "Shell access could not be enabled for cPanel account \"{$account->username}\". "
            .'Jailed shell is required for automated WordPress provisioning. '
            .'Enable shell access for this account/package in WHM or ask Krystal to grant the reseller the required shell-management permission.';
    }

    private function exactDomainAccounts(HostingServer $server, string $domain): array
    {
        $payload = $this->call($server, 'listaccts');
        $accounts = data_get($payload, 'data.acct');
        if (data_get($payload, 'metadata.result') !== 1 || ! is_array($accounts)) {
            Log::warning('WHM returned a malformed listaccts response during provisioning reconciliation.', [
                'top_level_keys' => array_keys($payload),
                'metadata_result' => data_get($payload, 'metadata.result'),
                'has_data' => array_key_exists('data', $payload),
                'has_accounts' => data_get($payload, 'data.acct') !== null,
            ]);
            throw new RuntimeException('WHM returned an invalid account list while confirming the cPanel account.');
        }

        $normalizedDomain = strtolower(rtrim($domain, '.'));
        return collect($accounts)
            ->filter(fn ($item) => is_array($item)
                && is_string($item['user'] ?? null)
                && trim($item['user']) !== ''
                && strtolower(rtrim((string) ($item['domain'] ?? ''), '.')) === $normalizedDomain)
            ->map(fn ($account) => $this->mapAccount($account))
            ->values()
            ->all();
    }

    private function singleDomainAccount(array $matches, string $domain): array
    {
        if (count($matches) === 1) return $matches[0];

        Log::warning('WHM domain reconciliation did not return exactly one account.', [
            'domain' => $domain,
            'match_count' => count($matches),
            'usernames' => collect($matches)->pluck('username')->filter()->values()->all(),
        ]);
        throw new RuntimeException(
            count($matches) === 0
                ? 'WHM reported account creation success, but the new cPanel account is not visible yet. Retry this provisioning run shortly.'
                : 'WHM returned multiple cPanel accounts for this primary domain. Provisioning stopped for safety.'
        );
    }

    private function mapAccount(array $account): array
    {
        return [
            'external_id' => (string) $account['user'],
            'username' => (string) $account['user'],
            'primary_domain' => $account['domain'] ?? null,
            'assigned_ip' => $account['ip'] ?? null,
            'package_name' => $account['plan'] ?? null,
            'status' => ($account['suspended'] ?? 0) ? 'suspended' : 'active',
            'disk_used_bytes' => $this->bytes($account['diskused'] ?? null),
            'disk_limit_bytes' => $this->bytes($account['disklimit'] ?? null),
            'metadata' => ['owner' => $account['owner'] ?? null, 'email' => $account['email'] ?? null],
        ];
    }

    private function redactCreateAccountSecrets(string $message, HostingServer $server, array $data): string
    {
        $credentials = $server->credentials ?? [];
        $secrets = array_filter([
            $data['password'] ?? null,
            $credentials['token'] ?? null,
        ], fn ($value) => is_string($value) && $value !== '');

        return Str::limit(str_replace($secrets, '[REDACTED]', strip_tags($message)), 240);
    }

    public function installWordpress(HostingServer $server, HostingAccount $account, array $data): array
    {
        throw new RuntimeException('WordPress installation must use the secure SSH provisioning pipeline.');
    }

    public function configureWordpress(HostingServer $server, HostingAccount $account, array $data): array
    {
        throw new RuntimeException('WordPress configuration must use the secure SSH provisioning pipeline.');
    }

    public function installAgent(HostingServer $server, HostingAccount $account, array $data): array
    {
        throw new RuntimeException('Automatic Site Agent installation is not configured for this server.');
    }

    public function terminateAccount(HostingServer $server, HostingAccount $account): array
    {
        if (config('hosting.termination_mode') !== 'live' || ! config('hosting.allow_live_termination') || ! app()->environment('production')) {
            throw new RuntimeException('Live hosting termination is disabled.');
        }
        $response = $this->call($server, 'removeacct', ['username' => $account->username, 'keepdns' => 0]);
        return ['terminated' => true, 'external_id' => $account->external_id, 'message' => $response['metadata']['reason'] ?? 'Terminated'];
    }

    public function accountMetrics(HostingServer $server, Website $website): array
    {
        $account = $website->hostingAccount;
        if (! $account) return [];
        $result=['disk_used_bytes'=>$account->disk_used_bytes,'disk_limit_bytes'=>$account->disk_limit_bytes,'bandwidth_used_bytes'=>$account->bandwidth_used_bytes,'bandwidth_limit_bytes'=>$account->bandwidth_limit_bytes];
        try {
            $stats=collect($this->uapi($server,$account,'StatsBar','get_stats'));
            $byId=$stats->filter(fn($row)=>is_array($row)&&isset($row['id']))->keyBy('id');
            $result=[...$result,
                'disk_used_bytes'=>$this->statBytes($byId->get('diskusage'),$account->disk_used_bytes),'disk_limit_bytes'=>$this->statLimitBytes($byId->get('diskusage'),$account->disk_limit_bytes),
                'bandwidth_used_bytes'=>$this->statBytes($byId->get('bandwidth'),$account->bandwidth_used_bytes),'bandwidth_limit_bytes'=>$this->statLimitBytes($byId->get('bandwidth'),$account->bandwidth_limit_bytes),
                'inode_used'=>$this->statValue($byId->get('inodeusage')),'inode_limit'=>$this->statMax($byId->get('inodeusage')),
                'database_count'=>$this->statValue($byId->get('sqldatabases')),'mailbox_count'=>$this->statValue($byId->get('emailaccounts')),
            ];
        } catch (RuntimeException) { /* Keep the last safe cached values when a reseller ACL omits StatsBar. */ }
        return array_filter($result,fn($value)=>$value!==null);
    }

    private function uapi(HostingServer $server,HostingAccount $account,string $module,string $function,array $parameters=[]):array
    {
        $response=$this->call($server,'cpanel',['cpanel_jsonapi_user'=>strtolower($account->username),'cpanel_jsonapi_apiversion'=>3,'cpanel_jsonapi_module'=>$module,'cpanel_jsonapi_func'=>$function,...$parameters]);
        $data=data_get($response,'data.result.data')??data_get($response,'data.uapi.result.data')??[];
        if(!is_array($data))throw new RuntimeException("WHM returned no {$module} metrics for this cPanel account.");
        return $data;
    }

    private function statValue(mixed $row):?int { $value=is_array($row)?($row['usage']??$row['value']??null):null; return is_numeric($value)?(int)$value:null; }
    private function statMax(mixed $row):?int { $value=is_array($row)?($row['maximum']??$row['max']??null):null; return is_numeric($value)?(int)$value:null; }
    private function statBytes(mixed $row,?int $fallback):?int { $value=$this->statValue($row); return $value===null?$fallback:$value*1024*1024; }
    private function statLimitBytes(mixed $row,?int $fallback):?int { $value=$this->statMax($row); return $value===null?$fallback:$value*1024*1024; }

    public function cpanelSession(HostingServer $server, HostingAccount $account): ?string
    {
        $response = $this->call($server, 'create_user_session', [
            'user' => $account->username,
            'service' => 'cpaneld',
        ]);

        return $response['data']['url'] ?? null;
    }

    private function bytes(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) round((float) $value * 1024 * 1024);
    }

    private function shellAccess(array $package): ?bool
    {
        foreach (['HASSHELL', 'hasshell', 'shell', 'SHELL', 'ssh', 'SSH'] as $key) {
            if (! array_key_exists($key, $package)) continue;
            $value = strtolower(trim((string) $package[$key]));
            if (in_array($value, ['1', 'true', 'on', 'enabled', 'yes', 'y', 'jailshell'], true)) return true;
            if (in_array($value, ['0', 'false', 'off', 'disabled', 'no', 'n', 'noshell'], true)) return false;

            return null;
        }

        return null;
    }
}
