<?php

namespace App\Services\Hosting;

use App\Contracts\HostingProviderInterface;
use App\Models\HostingServer;

class HostingProviderManager
{
    public function for(HostingServer $server): HostingProviderInterface
    {
        return match ($server->api_type) {
            'whm' => app(KrystalWhmProvider::class),
            'cpanel' => app(CpanelHostingProvider::class),
            default => app(MockHostingProvider::class),
        };
    }
}
