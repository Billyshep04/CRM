<?php

namespace App\Services\Hosting;

use App\Contracts\SslInspector;
use Carbon\Carbon;

class ProvisioningSslService
{
    public function __construct(private readonly SslInspector $inspector) {}

    public function inspect(string $domain): array
    {
        $root = $this->inspector->inspect($domain);
        $requireWww = ! $this->isSubdomain($domain);
        $www = $requireWww ? $this->inspector->inspect("www.{$domain}") : ['valid' => null, 'hostname_match' => null, 'status' => 'not_required'];
        $active = $root['valid'] && $root['hostname_match'] && (! $requireWww || ($www['valid'] && $www['hostname_match']));
        $expiry = $root['expires_at'] ?? null;
        return [
            'active' => $active,
            'ready' => $active,
            'status' => $active ? 'active' : 'pending',
            'root' => $root,
            'www' => $www,
            'www_required' => $requireWww,
            'issuer' => $root['issuer'] ?? null,
            'expires_at' => $expiry,
            'days_remaining' => $expiry ? now()->diffInDays(Carbon::parse($expiry), false) : null,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function isSubdomain(string $domain): bool
    {
        $labels = array_values(array_filter(explode('.', strtolower(rtrim($domain, '.')))));
        $secondLevelSuffixes = ['co.uk', 'org.uk', 'me.uk', 'ltd.uk', 'plc.uk', 'net.uk', 'ac.uk', 'gov.uk'];
        $suffix = implode('.', array_slice($labels, -2));
        $registrableLabels = in_array($suffix, $secondLevelSuffixes, true) ? 3 : 2;

        return count($labels) > $registrableLabels;
    }
}
