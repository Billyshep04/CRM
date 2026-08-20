<?php

namespace App\Services\Hosting;

use App\Contracts\HostingProviderInterface;
use App\Exceptions\ManualProvisioningActionRequired;
use App\Models\HostingAccount;
use App\Models\HostingServer;
use App\Models\Website;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class KrystalWhmProvider implements HostingProviderInterface
{
    private function client(HostingServer $server): PendingRequest
    {
        $credentials = $server->credentials ?? [];

        if (! $server->hostname || empty($credentials['username']) || empty($credentials['token'])) {
            throw new RuntimeException('WHM credentials are not configured.');
        }

        return Http::withHeaders([
            'Authorization' => 'whm '.$credentials['username'].':'.$credentials['token'],
        ])->acceptJson()
            ->connectTimeout(8)
            ->timeout(20)
            ->baseUrl('https://'.preg_replace('/:\d+$/', '', $server->hostname).':2087/json-api');
    }

    private function call(HostingServer $server, string $function, array $query = []): array
    {
        try {
            $response = $this->client($server)->get('/'.$function, [
                'api.version' => 1,
                ...$query,
            ]);
        } catch (ConnectionException) {
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
            ?? data_get($payload, 'data.reason')
            ?? data_get($payload, 'message');

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
        $response = $this->call($server, 'createacct', [
            'username' => $data['username'],
            'domain' => $data['domain'],
            'password' => $data['password'],
            'plan' => $data['package_name'],
        ]);

        return [
            'external_id' => $data['username'],
            'username' => $data['username'],
            'primary_domain' => $data['domain'],
            'package_name' => $data['package_name'],
            'status' => 'active',
            'metadata' => ['whm_message' => $response['metadata']['reason'] ?? 'Created'],
        ];
    }

    public function verifyAccount(HostingServer $server, HostingAccount $account): array
    {
        $match = collect($this->accounts($server))->first(fn ($item) => strtolower($item['username']) === strtolower($account->username));
        if (! $match) throw new RuntimeException('The new cPanel account is not visible in WHM yet. Retry this step shortly.');
        return ['ready' => true, 'status' => $match['status'] ?? 'active'];
    }

    public function installWordpress(HostingServer $server, HostingAccount $account, array $data): array
    {
        throw new ManualProvisioningActionRequired('WordPress installation needs a confirmed Softaculous integration for this Krystal server. The cPanel account is ready; install WordPress manually, then retry.');
    }

    public function configureWordpress(HostingServer $server, HostingAccount $account, array $data): array
    {
        throw new ManualProvisioningActionRequired('WordPress configuration needs the confirmed Krystal installer integration.');
    }

    public function installAgent(HostingServer $server, HostingAccount $account, array $data): array
    {
        throw new ManualProvisioningActionRequired('Automatic Site Agent installation needs the confirmed Krystal installer integration.');
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
}
