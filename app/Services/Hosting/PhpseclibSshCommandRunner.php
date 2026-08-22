<?php

namespace App\Services\Hosting;

use App\Contracts\SshCommandRunner;
use App\Models\HostingAccount;
use App\Models\HostingServer;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use RuntimeException;

class PhpseclibSshCommandRunner implements SshCommandRunner
{
    public function run(HostingServer $server, HostingAccount $account, string $password, string $command, int $timeout = 60): array
    {
        $host = preg_replace('/:\d+$/', '', trim((string) $server->hostname));
        if (! $host || ! preg_match('/^[a-z0-9.-]+$/i', $host)) throw new RuntimeException('The Krystal SSH hostname is invalid.');

        $ssh = new SSH2($host, (int) config('hosting.ssh.port', 722), (int) config('hosting.ssh.connect_timeout', 15));
        $ssh->setTimeout($timeout);
        $expected = trim((string) ($server->metadata['ssh_host_fingerprint'] ?? config('hosting.ssh.host_fingerprint')));
        if ($expected === '') throw new RuntimeException('SSH host verification is not configured. Add the Krystal SSH host fingerprint before provisioning.');
        try {
            $actual = PublicKeyLoader::load((string) $ssh->getServerPublicHostKey())->getFingerprint('sha256');
        } catch (\Throwable) {
            throw new RuntimeException('SSH host verification failed. The server key could not be validated.');
        }
        $normalise = fn (string $value) => strtolower(rtrim(str_replace(['SHA256:', 'sha256:', ':', ' '], '', trim($value)), '='));
        if (! hash_equals($normalise($expected), $normalise($actual))) throw new RuntimeException('SSH host verification failed. The Krystal server fingerprint does not match.');
        if (! $ssh->login($account->username, $password)) throw new RuntimeException('SSH connection failed. Check Shell Access and the cPanel credentials.');

        $output = (string) $ssh->exec($command);
        $status = $ssh->getExitStatus();
        return ['exit_code' => is_int($status) ? $status : 1, 'output' => $output];
    }
}
