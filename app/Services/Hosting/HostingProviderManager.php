<?php

namespace App\Services\Hosting;

use App\Contracts\HostingProviderInterface;
use App\Models\HostingServer;

class HostingProviderManager
{
    public function forMode(HostingServer $server, string $mode): HostingProviderInterface
    {
        return $mode === 'mock' ? app(MockHostingProvider::class) : $this->for($server);
    }

    public function for(HostingServer $server): HostingProviderInterface
    {
        return match ($server->api_type) {
            'whm' => app(KrystalWhmProvider::class),
            'cpanel' => app(CpanelHostingProvider::class),
            default => app(MockHostingProvider::class),
        };
    }
}
