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
        return ['hosting_status' => 'active', 'disk_used_bytes' => null, 'disk_limit_bytes' => null, 'bandwidth_used_bytes' => null, 'provider' => $server->provider];
    }
    public function accounts(HostingServer $server): array { return $server->metadata['mock_accounts'] ?? []; }
    public function packages(HostingServer $server): array { return $server->metadata['mock_packages'] ?? [['external_id'=>'webstamp-standard','name'=>'Web Stamp Standard','limits'=>[]]]; }
    public function createAccount(HostingServer $server, array $data): array { return ['external_id'=>$data['username'],'username'=>$data['username'],'primary_domain'=>$data['domain'],'package_name'=>$data['package_name'] ?? null,'status'=>'active']; }
    public function cpanelSession(HostingServer $server, HostingAccount $account): ?string { return null; }
}
