<?php

namespace App\Contracts;

use App\Models\HostingAccount;
use App\Models\HostingServer;

interface CpanelUapiClient
{
    public function call(HostingServer $server, HostingAccount $account, string $module, string $function, array $parameters = []): array;
}
