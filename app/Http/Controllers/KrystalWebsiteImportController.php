<?php

namespace App\Http\Controllers;

use App\Http\Resources\WebsiteResource;
use App\Models\HostingAccount;
use App\Models\HostingServer;
use App\Models\Website;
use App\Models\WebsiteActivity;
use App\Services\Hosting\HostingAccountSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class KrystalWebsiteImportController extends Controller
{
    public function discover(HostingServer $hostingServer, HostingAccountSyncService $sync)
    {
        try {
            $syncResult = $sync->sync($hostingServer);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $websites = Website::query()->with(['customer', 'hostingAccount'])->get();
        $domains = $hostingServer->accounts()->with('websites.customer')->get()
            ->flatMap(function (HostingAccount $account) use ($websites): array {
                return collect($this->accountDomains($account))->map(function (array $domainData) use ($account, $websites): array {
                    $domain = $this->normaliseDomain($domainData['domain']);
                    $website = $websites->first(fn (Website $site) => $this->normaliseDomain($site->domain ?: $site->login_url) === $domain);

                    return [
                        'domain' => $domain,
                        'domain_type' => $domainData['type'] ?? ($domain === $this->normaliseDomain($account->primary_domain) ? 'primary' : 'addon'),
                        'hosting_account_id' => $account->id,
                        'cpanel_username' => $account->username,
                        'package_name' => $account->package_name,
                        'hosting_status' => $account->status,
                        'website_id' => $website?->id,
                        'website_name' => $website?->name,
                        'customer_id' => $website?->customer_id,
                        'customer_name' => $website?->customer?->name,
                        'state' => $website?->hosting_account_id === $account->id
                            ? 'connected'
                            : ($website ? 'matched' : 'new'),
                        'monitoring_connected' => $website?->agent_last_seen_at !== null,
                    ];
                })->all();
            })
            ->unique('domain')
            ->sortBy('domain')
            ->values();

        return response()->json(['data' => [
            'server' => ['id' => $hostingServer->id, 'name' => $hostingServer->name],
            'domains' => $domains,
            'summary' => [
                'found' => $domains->count(),
                'connected' => $domains->where('state', 'connected')->count(),
                'matched' => $domains->where('state', 'matched')->count(),
                'new' => $domains->where('state', 'new')->count(),
            ],
            'warnings' => $syncResult['warnings'] ?? [],
        ]]);
    }

    public function import(Request $request, HostingAccount $hostingAccount)
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'wordpress_enabled' => ['sometimes', 'boolean'],
        ]);

        $domain = $this->normaliseDomain($data['domain']);
        $allowedDomains = collect($this->accountDomains($hostingAccount))
            ->pluck('domain')
            ->map(fn ($item) => $this->normaliseDomain($item));

        if (! $allowedDomains->contains($domain)) {
            throw ValidationException::withMessages(['domain' => ['This domain was not discovered on the selected Krystal account.']]);
        }

        $website = Website::query()->get()->first(
            fn (Website $site) => $this->normaliseDomain($site->domain ?: $site->login_url) === $domain
        );
        $token = null;

        if (! $website) {
            $token = Str::random(64);
            $website = Website::create([
                'customer_id' => $data['customer_id'],
                'hosting_server_id' => $hostingAccount->hosting_server_id,
                'hosting_account_id' => $hostingAccount->id,
                'name' => ($data['name'] ?? null) ?: $this->websiteName($domain),
                'domain' => $domain,
                'login_url' => "https://{$domain}",
                'environment' => 'production',
                'cpanel_username' => $hostingAccount->username,
                'wordpress_enabled' => $data['wordpress_enabled'] ?? true,
                'management_enabled' => true,
                'hosting_enabled' => true,
                'status' => 'unknown',
                'portal_visibility' => Website::defaultPortalVisibility(),
                'agent_token_hash' => hash('sha256', $token),
                'agent_token_encrypted' => $token,
            ]);
            $activityTitle = 'Website imported from Krystal';
        } else {
            $metadata = $website->metadata ?? [];
            unset($metadata['hosting_assignment_excluded']);
            $website->update([
                'customer_id' => $data['customer_id'],
                'hosting_server_id' => $hostingAccount->hosting_server_id,
                'hosting_account_id' => $hostingAccount->id,
                'cpanel_username' => $hostingAccount->username,
                'hosting_enabled' => true,
                'metadata' => $metadata,
            ]);
            $activityTitle = 'Krystal hosting connected';
        }

        WebsiteActivity::create([
            'website_id' => $website->id,
            'created_by_user_id' => $request->user()?->id,
            'type' => 'krystal_hosting_connected',
            'title' => $activityTitle,
            'description' => "Connected to cPanel account {$hostingAccount->username}.",
            'performed_at' => now(),
        ]);

        $resource = (new WebsiteResource($website->fresh()->load([
            'customer', 'hostingServer', 'hostingAccount', 'subscription', 'latestHealthCheck',
        ])))->resolve($request);

        return response()->json(['data' => $resource, 'agent_token' => $token]);
    }

    private function accountDomains(HostingAccount $account): array
    {
        $domains = collect($account->domains ?? [])->map(function ($item) use ($account): array {
            if (is_array($item)) return $item;

            return [
                'domain' => $item,
                'type' => $this->normaliseDomain($item) === $this->normaliseDomain($account->primary_domain) ? 'primary' : 'addon',
            ];
        });

        if ($account->primary_domain && ! $domains->contains(fn ($item) => $this->normaliseDomain($item['domain'] ?? '') === $this->normaliseDomain($account->primary_domain))) {
            $domains->prepend(['domain' => $account->primary_domain, 'type' => 'primary']);
        }

        return $domains->filter(fn ($item) => ! empty($item['domain']))->values()->all();
    }

    private function normaliseDomain(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $host = parse_url(str_contains($value, '://') ? $value : "https://{$value}", PHP_URL_HOST) ?: $value;

        return preg_replace('/^www\./', '', rtrim($host, '.'));
    }

    private function websiteName(string $domain): string
    {
        return Str::headline(str_replace(['-', '_'], ' ', explode('.', $domain)[0]));
    }
}
