<?php

namespace App\Services\WebsiteAnalysis;

use App\Exceptions\UnsafeWebsiteUrl;

class SafeUrlGuard
{
    public function assertSafe(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeWebsiteUrl('Only public HTTP and HTTPS URLs without embedded credentials may be audited.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || $this->isBlockedIp($host)) {
            throw new UnsafeWebsiteUrl('Private, reserved, and local network targets cannot be audited.');
        }

        if (! config('website-audits.enforce_public_networks', true)) {
            return;
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            throw new UnsafeWebsiteUrl('The website hostname could not be resolved.');
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || $this->isBlockedIp($ip)) {
                throw new UnsafeWebsiteUrl('The website resolves to a private or reserved network.');
            }
        }
    }

    private function isBlockedIp(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
