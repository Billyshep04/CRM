<?php

namespace App\Contracts;

use App\Models\HostingServer;
use App\Models\Website;
use App\Models\HostingAccount;

interface HostingProviderInterface
{
    public function testConnection(HostingServer $server): array;
    public function accountMetrics(HostingServer $server, Website $website): array;
    public function accounts(HostingServer $server): array;
    public function domains(HostingServer $server, HostingAccount $account): array;
    public function packages(HostingServer $server): array;
    public function createAccount(HostingServer $server, array $data): array;
    public function cpanelSession(HostingServer $server, HostingAccount $account): ?string;
}
