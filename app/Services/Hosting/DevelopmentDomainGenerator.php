<?php

namespace App\Services\Hosting;

use App\Models\HostingServer;
use App\Models\Website;
use App\Models\WebsiteProvisioningRun;
use Illuminate\Support\Str;
use RuntimeException;

class DevelopmentDomainGenerator
{
    public function __construct(private readonly HostingProviderManager $providers) {}

    public function generate(HostingServer $server, string $name): string
    {
        $base = $this->baseDomain($server);
        $slug = trim(Str::slug($name), '-');
        $maxSlugLength = max(1, min(58, 247 - strlen($base)));
        $slug = substr($slug ?: 'website', 0, $maxSlugLength);
        $provider = $this->providers->for($server);

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $domain = strtolower($slug.'-'.Str::lower(Str::random(4)).'.'.$base);
            if ($this->existsLocally($domain)) continue;
            if (method_exists($provider, 'domainOwners') && $provider->domainOwners($server, $domain) !== []) continue;

            return $domain;
        }

        throw new RuntimeException('A unique development address could not be generated. Try regenerating it.');
    }

    public function validateAvailable(HostingServer $server, string $domain): void
    {
        $domain = $this->normalise($domain);
        $base = $this->baseDomain($server);
        if (! str_ends_with($domain, '.'.$base)) {
            throw new RuntimeException("Development addresses must end in .{$base}.");
        }
        if ($this->existsLocally($domain)) throw new RuntimeException('This development address is already in use in the CRM.');
        $provider = $this->providers->for($server);
        if (method_exists($provider, 'domainOwners') && $provider->domainOwners($server, $domain) !== []) {
            throw new RuntimeException('This development address is already in use on Krystal.');
        }
    }

    public function baseDomain(HostingServer $server): string
    {
        $base = $this->normalise((string) ($server->metadata['development_base_domain'] ?? config('hosting.development_base_domain')));
        if (! preg_match('/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/', $base)) {
            throw new RuntimeException('The development base domain is not configured correctly.');
        }

        return $base;
    }

    private function existsLocally(string $domain): bool
    {
        return Website::query()->where(function ($query) use ($domain): void {
            $query->where('domain', $domain)->orWhere('current_domain', $domain)->orWhere('development_domain', $domain)->orWhere('production_domain', $domain);
        })->exists() || WebsiteProvisioningRun::where('domain', $domain)->whereNotIn('state', ['complete', 'failed'])->exists();
    }

    private function normalise(string $domain): string
    {
        $value = strtolower(trim($domain));
        $host = parse_url(str_contains($value, '://') ? $value : "https://{$value}", PHP_URL_HOST) ?: $value;

        return rtrim((string) $host, '.');
    }
}
