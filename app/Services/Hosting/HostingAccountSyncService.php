<?php

namespace App\Services\Hosting;

use App\Models\HostingAccount;
use App\Models\HostingPackage;
use App\Models\HostingServer;
use App\Models\Website;
use App\Models\HostingMetricSnapshot;
use RuntimeException;

class HostingAccountSyncService
{
    public function __construct(private HostingProviderManager $providers) {}

    public function sync(HostingServer $server): array
    {
        $provider = $this->providers->for($server);
        $warnings = [];

        $accounts = collect($provider->accounts($server))->map(function (array $data) use ($server, $provider, &$warnings): HostingAccount {
            $account = HostingAccount::updateOrCreate(
                ['hosting_server_id' => $server->id, 'external_id' => $data['external_id']],
                [...$data, 'last_synced_at' => now()]
            );

            try {
                $domains = $provider->domains($server, $account);
                $account->update(['domains' => $domains]);
            } catch (RuntimeException $exception) {
                $domains = $account->domains ?: ($account->primary_domain
                    ? [['domain' => $account->primary_domain, 'type' => 'primary']]
                    : []);
                $account->update(['domains' => $domains]);
                $warnings[] = "Could not read addon domains for {$account->username}: {$exception->getMessage()}";
            }

            $this->matchExistingWebsites($account);

            HostingMetricSnapshot::create(['hosting_account_id'=>$account->id,'website_id'=>$account->websites()->value('id'),'status'=>$account->status,'disk_used_bytes'=>$account->disk_used_bytes,'disk_limit_bytes'=>$account->disk_limit_bytes,'bandwidth_used_bytes'=>$account->bandwidth_used_bytes,'bandwidth_limit_bytes'=>$account->bandwidth_limit_bytes,'inode_used'=>$account->inode_used,'inode_limit'=>$account->inode_limit,'metrics'=>['source'=>'hosting_sync'],'captured_at'=>now()]);

            return $account->fresh();
        });

        try {
            $packages = collect($provider->packages($server))->map(fn (array $data) => HostingPackage::updateOrCreate(
                ['hosting_server_id' => $server->id, 'external_id' => $data['external_id']],
                [...$data, 'last_synced_at' => now(), 'active' => true]
            ));
        } catch (RuntimeException $exception) {
            $packages = collect();
            $warnings[] = $exception->getMessage();
        }

        return [
            'accounts' => $accounts->count(),
            'domains' => $accounts->sum(fn (HostingAccount $account) => count($account->domains ?? [])),
            'packages' => $packages->count(),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function matchExistingWebsites(HostingAccount $account): void
    {
        $domains = collect($account->domains ?? [])
            ->pluck('domain')
            ->push($account->primary_domain)
            ->filter()
            ->map(fn ($domain) => $this->normaliseDomain($domain))
            ->unique();

        Website::query()
            ->whereNull('hosting_account_id')
            ->where('hosting_enabled', true)
            ->get()
            ->each(function (Website $website) use ($account, $domains): void {
                if (data_get($website->metadata, 'hosting_assignment_excluded', false)) return;
                if (! $domains->contains($this->normaliseDomain($website->domain ?: $website->login_url))) return;

                $website->update([
                    'hosting_server_id' => $account->hosting_server_id,
                    'hosting_account_id' => $account->id,
                    'cpanel_username' => $account->username,
                    'hosting_enabled' => true,
                ]);
            });
    }

    private function normaliseDomain(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $host = parse_url(str_contains($value, '://') ? $value : "https://{$value}", PHP_URL_HOST) ?: $value;

        return preg_replace('/^www\./', '', rtrim($host, '.'));
    }
}
