<?php

namespace App\Services\Hosting;

use App\Contracts\HostingProviderInterface;
use App\Models\HostingServer;
use App\Models\Website;
use App\Models\HostingAccount;

class MockHostingProvider implements HostingProviderInterface
{
    public function testConnection(HostingServer $server): array { return ['ok' => true, 'message' => 'Mock provider connected.']; }
    public function accountMetrics(HostingServer $server, Website $website): array
    {
        return ['hosting_status' => 'active', 'disk_used_bytes' => 1073741824, 'disk_limit_bytes' => 10737418240, 'bandwidth_used_bytes' => 2147483648, 'bandwidth_limit_bytes'=>53687091200, 'inode_used'=>12000, 'inode_limit'=>250000, 'database_count'=>1, 'mailbox_count'=>2, 'php_version'=>'8.3', 'ssl_status'=>'valid', 'provider' => $server->provider];
    }
    public function accounts(HostingServer $server): array { return $server->metadata['mock_accounts'] ?? []; }
    public function domains(HostingServer $server, HostingAccount $account): array
    {
        $domains = $account->domains ?: array_filter([$account->primary_domain]);

        return collect($domains)->map(function ($domain, $key) use ($account): array {
            if (is_array($domain)) return $domain;

            return [
                'domain' => $domain,
                'type' => $domain === $account->primary_domain ? 'primary' : (is_string($key) ? $key : 'addon'),
            ];
        })->values()->all();
    }
    public function packages(HostingServer $server): array { return $server->metadata['mock_packages'] ?? [['external_id'=>'webstamp-standard','name'=>'Web Stamp Standard','limits'=>[]]]; }
    public function createAccount(HostingServer $server, array $data): array { $this->fail($server,'create_cpanel_account'); return ['external_id'=>$data['username'],'username'=>$data['username'],'primary_domain'=>$data['domain'],'package_name'=>$data['package_name'] ?? null,'status'=>'active','domains'=>[$data['domain']],'metadata'=>['mock'=>true]]; }
    public function verifyAccount(HostingServer $server, HostingAccount $account): array { $this->fail($server,'wait_for_cpanel'); return ['ready'=>true,'username'=>$account->username]; }
    public function installWordpress(HostingServer $server, HostingAccount $account, array $data): array { $this->fail($server,'install_wordpress'); return ['installed'=>true,'url'=>'https://'.$account->primary_domain,'admin_username'=>$data['admin_username']]; }
    public function configureWordpress(HostingServer $server, HostingAccount $account, array $data): array { $this->fail($server,'configure_wordpress'); return ['configured'=>true,'profile_id'=>$data['profile_id']??null]; }
    public function installAgent(HostingServer $server, HostingAccount $account, array $data): array { $this->fail($server,'install_agent'); return ['installed'=>true,'connected'=>true]; }
    public function terminateAccount(HostingServer $server, HostingAccount $account): array { $this->fail($server,'terminate_account'); return ['terminated'=>true,'external_id'=>$account->external_id]; }
    public function cpanelSession(HostingServer $server, HostingAccount $account): ?string { return null; }
    private function fail(HostingServer $server,string $step):void { if(in_array($step,$server->metadata['mock_fail_steps']??[],true)) throw new \RuntimeException("Mock {$step} failure."); }
}
