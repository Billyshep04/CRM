<?php

namespace App\Contracts;

use App\Models\HostingServer;
use App\Models\Website;

interface HostingProviderInterface
{
    public function testConnection(HostingServer $server): array;
    public function accountMetrics(HostingServer $server, Website $website): array;
}
