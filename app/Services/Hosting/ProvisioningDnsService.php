<?php

namespace App\Services\Hosting;

use App\Contracts\DnsResolver;

class ProvisioningDnsService
{
    public function __construct(private readonly DnsResolver $resolver) {}

    public function inspect(string $domain, string $expectedIp): array
    {
        $root = $this->resolver->aRecords($domain);
        $wwwA = $this->resolver->aRecords("www.{$domain}");
        $wwwCname = $this->resolver->cnameRecords("www.{$domain}");
        $nameservers = $this->resolver->nameservers($domain);
        $rootCorrect = in_array($expectedIp, $root, true);
        $wwwCorrect = in_array($expectedIp, $wwwA, true) || in_array($domain, $wwwCname, true);

        return [
            'correct' => $rootCorrect && $wwwCorrect,
            'ready' => $rootCorrect && $wwwCorrect,
            'root' => $this->status($root, $expectedIp, $rootCorrect),
            'www' => ['status' => $wwwCorrect ? 'correct' : (($wwwA || $wwwCname) ? 'incorrect' : 'missing'), 'current_a' => $wwwA, 'current_cname' => $wwwCname, 'expected' => $domain],
            'expected_ip' => $expectedIp,
            'nameservers' => $nameservers,
            'provider' => $this->provider($nameservers),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function status(array $records, string $expectedIp, bool $correct): array
    {
        return ['status' => $correct ? 'correct' : ($records ? 'incorrect' : 'missing'), 'current' => $records, 'expected' => $expectedIp];
    }

    private function provider(array $nameservers): array
    {
        $joined = implode(' ', $nameservers);
        return match (true) {
            str_contains($joined, 'cloudflare.com') => ['key' => 'cloudflare', 'label' => 'Cloudflare', 'confidence' => 'high'],
            str_contains($joined, 'registrar-servers.com') => ['key' => 'namecheap', 'label' => 'Namecheap', 'confidence' => 'likely'],
            str_contains($joined, 'krystal') || str_contains($joined, 'cloudhosting') => ['key' => 'krystal', 'label' => 'Krystal', 'confidence' => 'likely'],
            default => ['key' => 'external', 'label' => $nameservers ? 'External DNS provider' : 'Unknown DNS provider', 'confidence' => 'unknown'],
        };
    }
}
