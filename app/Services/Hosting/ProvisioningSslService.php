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
        $www = $this->inspector->inspect("www.{$domain}");
        $active = $root['valid'] && $root['hostname_match'] && $www['valid'] && $www['hostname_match'];
        $expiry = $root['expires_at'] ?? null;
        return [
            'active' => $active,
            'ready' => $active,
            'status' => $active ? 'active' : 'pending',
            'root' => $root,
            'www' => $www,
            'issuer' => $root['issuer'] ?? null,
            'expires_at' => $expiry,
            'days_remaining' => $expiry ? now()->diffInDays(Carbon::parse($expiry), false) : null,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
