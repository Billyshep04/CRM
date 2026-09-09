<?php

namespace App\Services\Hosting;

use App\Contracts\HostingProviderInterface;
use App\Exceptions\CpanelUsernameUnavailable;
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
        if (($data['shell_access'] ?? false) === true) {
            $query['hasshell'] = 1;
        }
        try {
            $response = $this->call(
                $server,
                'createacct',
                $query,
                max(30, (int) config('hosting.whm_account_creation_timeout_seconds', 120))
            );
        } catch (RuntimeException $exception) {
            $safe = $this->redactCreateAccountSecrets($exception->getMessage(), $server, $data);
            if ($this->isUsernameUnavailable($safe)) {
                $created = $this->exactDomainAccounts($server, $domain);
                if ($created !== []) {
                    $match = $this->singleDomainAccount($created, $domain);
                    if (strtolower($match['username']) === $proposedUsername) {
                        Log::info('WHM username rejection reconciled an account that was created.', [
                            'domain' => $domain,
                            'authoritative_username' => $match['username'],
                        ]);

                        return [
                            ...$match,
                            'package_name' => $match['package_name'] ?? $data['package_name'],
                            'metadata' => [...($match['metadata'] ?? []), 'reconciled_after_username_response' => true],
                        ];
                    }

                    throw new RuntimeException(
                        "This domain already exists on Krystal under cPanel account \"{$match['username']}\". Link the existing hosting account explicitly or use another domain."
                    );
                }

                Log::info('WHM rejected an unavailable cPanel username.', [
                    'domain' => $domain,
                    'proposed_username' => $proposedUsername,
                ]);
                throw new CpanelUsernameUnavailable('The generated cPanel username is unavailable.', 0, $exception);
            }
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

    private function isUsernameUnavailable(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'reserved username')
            || preg_match('/\busername\b.{0,120}\b(?:already exists|already in use|unavailable|not available|reserved)\b/', $message) === 1
            || preg_match('/\buser\b.{0,80}\busername\b.{0,120}\balready exists\b/', $message) === 1;
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

    public function verifyUsableShell(HostingServer $server, HostingAccount $account): array
    {
        try {
            $shell = $this->accountShell($server, $account);
        } catch (RuntimeException) {
            Log::warning('WHM shell state could not be inspected.', [
                'hosting_account_id' => $account->id,
                'username' => $account->username,
            ]);

            return ['shell' => null, 'changed' => false, 'inspection' => 'unavailable'];
        }
        if ($this->isDisabledShell($shell)) {
            Log::warning('WHM reported unusable shell access.', [
                'hosting_account_id' => $account->id,
                'username' => $account->username,
                'reported_shell' => $shell ?: 'unknown',
            ]);
            throw new RuntimeException($this->shellAccessFailure($account));
        }

        Log::info('WHM reported usable shell access.', [
            'hosting_account_id' => $account->id,
            'username' => $account->username,
            'shell' => $shell,
        ]);

        return ['shell' => $shell, 'changed' => false];
    }

    public function domainOwners(HostingServer $server, string $domain): array
    {
        $domain = strtolower(rtrim($domain, '.'));
        $owners = [];
        foreach ($this->accounts($server) as $remote) {
            $account = new HostingAccount([
                'hosting_server_id' => $server->id,
                'external_id' => $remote['external_id'],
                'username' => $remote['username'],
                'primary_domain' => $remote['primary_domain'],
            ]);
            foreach ($this->domains($server, $account) as $item) {
                if (strtolower(rtrim((string) ($item['domain'] ?? ''), '.')) === $domain) {
                    $owners[] = ['username' => $remote['username'], 'type' => $item['type'] ?? 'unknown'];
                }
            }
        }

        return collect($owners)->unique(fn ($owner) => $owner['username'].'|'.$owner['type'])->values()->all();
    }

    public function changePrimaryDomain(HostingServer $server, HostingAccount $account, string $domain): array
    {
        $domain = strtolower(rtrim($domain, '.'));
        $existingTarget = $this->exactDomainAccounts($server, $domain);
        if ($existingTarget !== []) {
            $match = $this->singleDomainAccount($existingTarget, $domain);
            if (strtolower($match['username']) !== strtolower($account->username)) {
                throw new RuntimeException("The production domain already belongs to cPanel account \"{$match['username']}\". Launch stopped for safety.");
            }

            return $match;
        }
        if (strtolower(rtrim((string) $account->primary_domain, '.')) !== $domain) {
            $payload = $this->call($server, 'modifyacct', ['user' => $account->username, 'domain' => $domain], 120);
            if (data_get($payload, 'metadata.result') !== 1) {
                throw new RuntimeException($this->safeWhmReason($payload) ?: 'WHM could not change the cPanel account domain.');
            }
        }

        $match = $this->singleDomainAccount($this->exactDomainAccounts($server, $domain), $domain);
        if (strtolower($match['username']) !== strtolower($account->username)) {
            throw new RuntimeException('WHM returned a different cPanel account after the domain change. Launch stopped for safety.');
        }

        return $match;
    }

    public function ensureAddonDomain(HostingServer $server, HostingAccount $account, string $domain, ?string $developmentDomain = null): array
    {
        $domain = strtolower(rtrim(trim($domain), '.'));
        $hasLaunchIdentity = is_string($developmentDomain) && trim($developmentDomain) !== '';
        $developmentDomain = strtolower(rtrim(trim((string) ($developmentDomain ?: $account->primary_domain)), '.'));
        $internalLabel = $this->addonInternalSubdomainLabel($domain);
        $internalDomain = $internalLabel.'.'.$developmentDomain;
        $owners = $this->domainOwners($server, $domain);

        if ($owners !== []) {
            if (count($owners) !== 1 || strtolower((string) ($owners[0]['username'] ?? '')) !== strtolower($account->username)) {
                $owner = (string) ($owners[0]['username'] ?? 'another account');
                throw new RuntimeException("The production domain already belongs to cPanel account \"{$owner}\". Launch stopped for safety.");
            }

            return [
                ...$this->verifyAddonDomain($server, $account, $domain),
                'internal_subdomain' => $internalDomain,
                'residue_cleaned' => false,
            ];
        }

        $existingAddon = $this->addonDomainOnAccount($server, $account, $domain);
        if ($existingAddon !== null) {
            return [
                ...$existingAddon,
                'internal_subdomain' => $internalDomain,
                'residue_cleaned' => false,
            ];
        }

        $residueCleaned = $this->cleanupOwnedAddonResidue(
            $server,
            $account,
            $domain,
            $developmentDomain,
            $internalLabel,
            $internalDomain,
            $hasLaunchIdentity
        );

        $payload = $this->call($server, 'cpanel', [
            'cpanel_jsonapi_user' => strtolower($account->username),
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'AddonDomain',
            'cpanel_jsonapi_func' => 'addaddondomain',
            'newdomain' => $domain,
            'dir' => 'public_html',
            'subdomain' => $internalLabel,
            'ftp_is_optional' => 1,
        ], 120);
        try {
            $this->requireCpanelApi2Success(
                $server,
                $payload,
                'addaddondomain',
                'cPanel could not add the production domain to this hosting account.',
                ['hosting_account_id' => $account->id, 'username' => $account->username, 'domain' => $domain]
            );
        } catch (RuntimeException $exception) {
            if (! $this->isInternalSubdomainDnsConflict($exception, $internalDomain)) {
                throw $exception;
            }

            $existingAddon = $this->addonDomainOnAccount($server, $account, $domain);
            if ($existingAddon !== null) {
                return [
                    ...$existingAddon,
                    'internal_subdomain' => $internalDomain,
                    'residue_cleaned' => $residueCleaned,
                ];
            }

            $cleanedAfterFailure = $this->cleanupOwnedAddonResidue(
                $server,
                $account,
                $domain,
                $developmentDomain,
                $internalLabel,
                $internalDomain,
                $hasLaunchIdentity
            );
            if (! $cleanedAfterFailure) {
                throw new RuntimeException(
                    "cPanel reports that the internal domain \"{$internalDomain}\" already exists, but the CRM could not prove it is owned residue from this launch. Manual review is required; nothing was deleted."
                );
            }

            $residueCleaned = true;
            $retryPayload = $this->call($server, 'cpanel', [
                'cpanel_jsonapi_user' => strtolower($account->username),
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => 'AddonDomain',
                'cpanel_jsonapi_func' => 'addaddondomain',
                'newdomain' => $domain,
                'dir' => 'public_html',
                'subdomain' => $internalLabel,
                'ftp_is_optional' => 1,
            ], 120);
            $this->requireCpanelApi2Success(
                $server,
                $retryPayload,
                'addaddondomain',
                'cPanel could not add the production domain after safely removing its residual internal subdomain.',
                ['hosting_account_id' => $account->id, 'username' => $account->username, 'domain' => $domain, 'retry' => true]
            );
        }

        Log::info('Production addon domain creation succeeded.', [
            'hosting_account_id' => $account->id,
            'username' => $account->username,
            'domain' => $domain,
            'document_root' => 'public_html',
        ]);

        return [
            ...$this->verifyAddonDomain($server, $account, $domain),
            'internal_subdomain' => $internalDomain,
            'residue_cleaned' => $residueCleaned,
        ];
    }

    private function addonInternalSubdomainLabel(string $domain): string
    {
        return 'ws'.substr(hash('sha256', strtolower(rtrim(trim($domain), '.'))), 0, 10);
    }

    private function cleanupOwnedAddonResidue(
        HostingServer $server,
        HostingAccount $account,
        string $productionDomain,
        string $developmentDomain,
        string $internalLabel,
        string $internalDomain,
        bool $hasLaunchIdentity
    ): bool {
        $primaryDomain = strtolower(rtrim(trim((string) $account->primary_domain), '.'));
        if ($developmentDomain === '' || $primaryDomain !== $developmentDomain) {
            throw new RuntimeException('The CRM cannot safely reconcile the internal addon-domain state because the launch development domain does not match the cPanel primary domain. Manual review is required; nothing was deleted.');
        }
        if ($internalDomain === $developmentDomain || $internalDomain === $productionDomain) {
            throw new RuntimeException('The CRM refused to clean up an unsafe addon-domain target. Manual review is required; nothing was deleted.');
        }

        $matches = $this->subdomains($server, $account)
            ->filter(fn ($item) => strtolower(rtrim(trim((string) ($item['domain'] ?? '')), '.')) === $internalDomain)
            ->values();
        if ($matches->isEmpty()) {
            return false;
        }
        if (! $hasLaunchIdentity) {
            throw new RuntimeException("The internal domain \"{$internalDomain}\" exists, but no launch identity was supplied to prove it belongs to this Go Live run. Manual review is required; nothing was deleted.");
        }
        if ($matches->count() !== 1) {
            throw new RuntimeException("The internal domain \"{$internalDomain}\" has ambiguous cPanel state. Manual review is required; nothing was deleted.");
        }

        $item = $matches->first();
        $rootDomain = strtolower(rtrim(trim((string) ($item['rootdomain'] ?? '')), '.'));
        $subdomain = strtolower(trim((string) ($item['subdomain'] ?? '')));
        $basedir = trim((string) ($item['basedir'] ?? ''), '/');
        $reldir = trim((string) ($item['reldir'] ?? ''));
        $absolute = rtrim((string) ($item['dir'] ?? ''), '/');
        $expectedAbsolute = '/home/'.strtolower($account->username).'/public_html';
        $sameDocumentRoot = $basedir === 'public_html'
            || $reldir === 'home:public_html'
            || strtolower($absolute) === strtolower($expectedAbsolute);
        $ownedResidue = $rootDomain === $developmentDomain
            && $subdomain === $internalLabel
            && $sameDocumentRoot;

        if (! $ownedResidue) {
            Log::warning('Addon-domain residue ownership could not be proven.', [
                'hosting_account_id' => $account->id,
                'username' => $account->username,
                'production_domain' => $productionDomain,
                'development_domain' => $developmentDomain,
                'internal_domain' => $internalDomain,
                'reported_root_domain' => $rootDomain,
                'reported_subdomain' => $subdomain,
                'reported_basedir' => $basedir,
            ]);
            throw new RuntimeException("The internal domain \"{$internalDomain}\" exists, but the CRM cannot prove it is owned residue from this launch. Manual review is required; nothing was deleted.");
        }

        $payload = $this->call($server, 'cpanel', [
            'cpanel_jsonapi_user' => strtolower($account->username),
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'SubDomain',
            'cpanel_jsonapi_func' => 'delsubdomain',
            'domain' => $internalDomain,
        ], 120);
        $this->requireCpanelApi2Success(
            $server,
            $payload,
            'delsubdomain',
            'cPanel could not safely remove the residual internal subdomain.',
            ['hosting_account_id' => $account->id, 'username' => $account->username, 'domain' => $internalDomain]
        );

        Log::info('Safely removed owned addon-domain residue.', [
            'hosting_account_id' => $account->id,
            'username' => $account->username,
            'production_domain' => $productionDomain,
            'development_domain' => $developmentDomain,
            'internal_domain' => $internalDomain,
        ]);

        return true;
    }

    private function subdomains(HostingServer $server, HostingAccount $account): \Illuminate\Support\Collection
    {
        $payload = $this->call($server, 'cpanel', [
            'cpanel_jsonapi_user' => strtolower($account->username),
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'SubDomain',
            'cpanel_jsonapi_func' => 'listsubdomains',
        ]);
        $result = $this->requireCpanelApi2Success(
            $server,
            $payload,
            'listsubdomains',
            'cPanel could not inspect internal subdomains before adding the production domain.',
            ['hosting_account_id' => $account->id, 'username' => $account->username]
        );

        return collect($result['data'] ?? [])->filter(fn ($item) => is_array($item))->values();
    }

    private function isInternalSubdomainDnsConflict(RuntimeException $exception, string $internalDomain): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'dns entry for the domain')
            && str_contains($message, 'already exists')
            && str_contains($message, strtolower($internalDomain));
    }

    public function verifyAddonDomain(HostingServer $server, HostingAccount $account, string $domain): array
    {
        $domain = strtolower(rtrim(trim($domain), '.'));
        $match = $this->addonDomainOnAccount($server, $account, $domain);
        if ($match === null) {
            throw new RuntimeException('The production addon domain is not visible on the expected cPanel account yet. Retry this launch shortly.');
        }

        return $match;
    }

    private function addonDomainOnAccount(HostingServer $server, HostingAccount $account, string $domain): ?array
    {
        $payload = $this->call($server, 'cpanel', [
            'cpanel_jsonapi_user' => strtolower($account->username),
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'AddonDomain',
            'cpanel_jsonapi_func' => 'listaddondomains',
        ]);
        $result = $this->requireCpanelApi2Success(
            $server,
            $payload,
            'listaddondomains',
            'cPanel could not verify the production addon domain.',
            ['hosting_account_id' => $account->id, 'username' => $account->username, 'domain' => $domain]
        );
        $matches = collect($result['data'] ?? [])
            ->filter(fn ($item) => is_array($item) && strtolower(rtrim((string) ($item['domain'] ?? ''), '.')) === $domain)
            ->values();

        if ($matches->isEmpty()) {
            return null;
        }
        if ($matches->count() !== 1) {
            throw new RuntimeException('cPanel returned ambiguous addon-domain records for the production domain. Manual review is required.');
        }

        $item = $matches->first();
        $basedir = trim((string) ($item['basedir'] ?? ''), '/');
        $reldir = trim((string) ($item['reldir'] ?? ''));
        $absolute = rtrim((string) ($item['dir'] ?? ''), '/');
        $expectedAbsolute = '/home/'.strtolower($account->username).'/public_html';
        $sameDocumentRoot = $basedir === 'public_html'
            || $reldir === 'home:public_html'
            || strtolower($absolute) === strtolower($expectedAbsolute);

        if (! $sameDocumentRoot) {
            Log::warning('Production addon domain uses an unexpected document root.', [
                'hosting_account_id' => $account->id,
                'username' => $account->username,
                'domain' => $domain,
                'basedir' => $basedir,
            ]);
            throw new RuntimeException('The production domain exists on this cPanel account but does not use the development website document root. Launch stopped for safety.');
        }

        return [
            'domain' => $domain,
            'username' => $account->username,
            'type' => 'addon',
            'document_root' => 'public_html',
        ];
    }

    private function requireCpanelApi2Success(HostingServer $server, array $payload, string $function, string $fallback, array $context = []): array
    {
        [$result, $resultPath] = match (true) {
            is_array(data_get($payload, 'data.cpanelresult')) => [data_get($payload, 'data.cpanelresult'), 'data.cpanelresult'],
            is_array(data_get($payload, 'data.result')) => [data_get($payload, 'data.result'), 'data.result'],
            is_array(data_get($payload, 'cpanelresult')) => [data_get($payload, 'cpanelresult'), 'cpanelresult'],
            is_array(data_get($payload, 'data')) && array_key_exists('event', $payload['data']) => [$payload['data'], 'data'],
            default => [null, null],
        };

        if (! is_array($result)) {
            Log::warning('WHM returned a malformed cPanel API 2 response.', [
                ...$context,
                'function' => $function,
                'top_level_keys' => array_values(array_map('strval', array_keys($payload))),
                'metadata_result' => data_get($payload, 'metadata.result'),
                'has_data' => array_key_exists('data', $payload),
            ]);
            throw new RuntimeException($fallback);
        }

        $data = $result['data'] ?? [];
        $items = is_array($data) ? (array_is_list($data) ? $data : [$data]) : [];
        $eventResult = data_get($result, 'event.result');
        $functionResults = collect($items)
            ->filter(fn ($item) => is_array($item) && array_key_exists('result', $item))
            ->pluck('result')
            ->values();
        $statuses = $function === 'addaddondomain'
            ? collect($items)
                ->filter(fn ($item) => is_array($item) && array_key_exists('status', $item))
                ->pluck('status')
                ->values()
            : collect();
        $messages = $this->safeCpanelApi2Messages($server, $result, $items);
        $failed = (int) data_get($payload, 'metadata.result', 0) !== 1
            || (int) $eventResult !== 1
            || $functionResults->contains(fn ($value) => (int) $value !== 1)
            || $statuses->contains(fn ($value) => (int) $value !== 1)
            || $this->hasCpanelApi2Errors($result, $items);

        if ($failed) {
            Log::warning('cPanel API 2 addon-domain operation failed.', [
                ...$context,
                'function' => $function,
                'result_path' => $resultPath,
                'metadata_result' => data_get($payload, 'metadata.result'),
                'event_result' => $eventResult,
                'function_results' => $functionResults->map(fn ($value) => is_scalar($value) ? (string) $value : get_debug_type($value))->all(),
                'statuses' => $statuses->map(fn ($value) => is_scalar($value) ? (string) $value : get_debug_type($value))->all(),
                'messages' => $messages,
                'data_type' => get_debug_type($data),
                'data_count' => is_array($data) ? count($data) : null,
            ]);
            throw new RuntimeException($messages === [] ? $fallback : $fallback.' '.implode(' ', $messages));
        }

        return $result;
    }

    private function hasCpanelApi2Errors(array $result, array $items): bool
    {
        $event = is_array($result['event'] ?? null) ? $result['event'] : [];
        $values = [
            $result['errors'] ?? null,
            $result['error'] ?? null,
            $event['errors'] ?? null,
            $event['error'] ?? null,
        ];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $values[] = $item['errors'] ?? null;
            $values[] = $item['error'] ?? null;
        }

        return collect($values)->flatten()->contains(fn ($value) => is_scalar($value) && trim((string) $value) !== '');
    }

    private function safeCpanelApi2Messages(HostingServer $server, array $result, array $items): array
    {
        $event = is_array($result['event'] ?? null) ? $result['event'] : [];
        $values = [
            $result['reason'] ?? null,
            $result['errors'] ?? null,
            $result['error'] ?? null,
            $result['messages'] ?? null,
            $event['reason'] ?? null,
            $event['errors'] ?? null,
            $event['error'] ?? null,
            $event['messages'] ?? null,
            $event['message'] ?? null,
        ];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (['reason', 'errors', 'error', 'messages', 'message'] as $key) {
                $values[] = $item[$key] ?? null;
            }
        }
        $credentials = $server->credentials ?? [];
        $secrets = collect([$credentials['token'] ?? null, $credentials['password'] ?? null])
            ->filter(fn ($value) => is_string($value) && $value !== '');

        return collect($values)
            ->flatten()
            ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
            ->map(function ($value) use ($secrets) {
                $message = trim(strip_tags((string) $value));

                foreach ($secrets as $secret) {
                    $message = str_replace($secret, '[REDACTED]', $message);
                }

                return Str::limit(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $message) ?? '', 300);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isDisabledShell(string $shell): bool
    {
        $shell = strtolower(trim($shell));

        return $shell === '' || in_array(basename($shell), ['noshell', 'nologin', 'false'], true);
    }

    private function accountShell(HostingServer $server, HostingAccount $account): string
    {
        $payload = $this->call($server, 'accountsummary', ['user' => $account->username]);
        $accounts = data_get($payload, 'data.acct');
        if (! is_array($accounts) || count($accounts) !== 1 || ! is_array($accounts[0])) {
            throw new RuntimeException('WHM returned an invalid account summary while checking shell access.');
        }

        $username = strtolower(trim((string) ($accounts[0]['user'] ?? '')));
        if ($username !== strtolower($account->username)) {
            throw new RuntimeException('WHM returned the wrong account while checking shell access.');
        }

        return trim((string) ($accounts[0]['shell'] ?? ''));
    }

    private function shellAccessFailure(HostingAccount $account): string
    {
        return "Usable SSH access could not be confirmed for cPanel account \"{$account->username}\". "
            .'Enable shell access for this account/package in WHM or ask Krystal to confirm the account can execute SSH commands.';
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
