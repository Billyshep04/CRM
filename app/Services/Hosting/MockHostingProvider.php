<?php

namespace App\Services\Hosting;

use App\Contracts\HostingProviderInterface;
use App\Models\HostingServer;
use App\Models\Website;

class MockHostingProvider implements HostingProviderInterface
{
    public function testConnection(HostingServer $server): array { return ['ok' => true, 'message' => 'Mock provider connected.']; }
    public function accountMetrics(HostingServer $server, Website $website): array
    {
        return ['hosting_status' => 'active', 'disk_used_bytes' => null, 'disk_limit_bytes' => null, 'bandwidth_used_bytes' => null, 'provider' => $server->provider];
    }
}
