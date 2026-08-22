<?php

namespace App\Services\Hosting;

use App\Contracts\HostingProviderInterface;
use App\Models\HostingServer;
use RuntimeException;

class HostingProviderManager
{
    public function forMode(HostingServer $server, string $mode): HostingProviderInterface
    {
        if ($mode === 'mock') return $this->mock();

        return $this->for($server);
    }

    public function for(HostingServer $server): HostingProviderInterface
    {
        return match ($server->api_type) {
            'whm' => app(KrystalWhmProvider::class),
            'cpanel' => app(CpanelHostingProvider::class),
            'mock' => $this->mock(),
            default => throw new RuntimeException('The hosting server API type is not supported.'),
        };
    }

    private function mock(): HostingProviderInterface
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Mock hosting is disabled outside local and test environments.');
        }

        return app(MockHostingProvider::class);
    }
}
