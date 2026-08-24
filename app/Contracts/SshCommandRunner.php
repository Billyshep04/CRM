<?php

namespace App\Contracts;

use App\Models\HostingAccount;
use App\Models\HostingServer;

interface SshCommandRunner
{
    /** @return array{exit_code:int, stdout:string, stderr:string} */
    public function run(HostingServer $server, HostingAccount $account, string $password, string $command, int $timeout = 60): array;
}
