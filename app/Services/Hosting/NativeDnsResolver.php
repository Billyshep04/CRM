<?php

namespace App\Services\Hosting;

use App\Contracts\DnsResolver;

class NativeDnsResolver implements DnsResolver
{
    public function aRecords(string $host): array { return collect(dns_get_record($host, DNS_A) ?: [])->pluck('ip')->filter()->unique()->values()->all(); }
    public function cnameRecords(string $host): array { return collect(dns_get_record($host, DNS_CNAME) ?: [])->pluck('target')->filter()->map(fn ($value) => strtolower(rtrim($value, '.')))->unique()->values()->all(); }
    public function nameservers(string $host): array { return collect(dns_get_record($host, DNS_NS) ?: [])->pluck('target')->filter()->map(fn ($value) => strtolower(rtrim($value, '.')))->unique()->values()->all(); }
}
