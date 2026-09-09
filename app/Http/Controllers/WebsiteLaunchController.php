<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebsiteLaunch;
use App\Models\Website;
use App\Models\WebsiteLaunchRun;
use App\Services\Hosting\HostingProviderManager;
use App\Services\Hosting\KrystalWhmProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebsiteLaunchController extends Controller
{
    public function preflight(Request $request, Website $website, HostingProviderManager $providers)
    {
        $data = $request->validate([
            'production_domain' => ['nullable', 'string', 'max:253', 'regex:/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/i'],
        ]);
        $website->load(['hostingServer', 'hostingAccount']);
        $issues = [];
        if ($website->environment !== 'development') $issues[] = 'This website is not marked as a development website.';
        if (! $website->hostingServer || ! $website->hostingAccount) $issues[] = 'A verified Krystal hosting account must be linked.';
        if ($website->hostingAccount?->provider_missing) $issues[] = 'The linked hosting account is no longer present in Krystal.';
        if (! $website->hostingAccount?->automation_password_encrypted) $issues[] = 'Automation credentials are not available for this development account.';
        $provider = $website->hostingServer ? $providers->for($website->hostingServer) : null;
        if ($website->hostingServer && ! $provider instanceof KrystalWhmProvider) $issues[] = 'Go Live currently requires a Krystal WHM connection.';

        if (! empty($data['production_domain'])) {
            $domain = strtolower(rtrim(trim($data['production_domain']), '.'));
            $localConflict = Website::whereKeyNot($website->id)->where(function ($query) use ($domain) {
                $query->where('domain', $domain)->orWhere('current_domain', $domain)->orWhere('development_domain', $domain)->orWhere('production_domain', $domain);
            })->exists();
            if ($localConflict) $issues[] = 'This domain is already used by another CRM website.';
            if ($provider instanceof KrystalWhmProvider) {
                $owners = $provider->domainOwners($website->hostingServer, $domain);
                if ($owners !== [] && (count($owners) !== 1 || strtolower((string) ($owners[0]['username'] ?? '')) !== strtolower((string) $website->hostingAccount?->username))) {
                    $issues[] = 'This domain already exists on Krystal. Resolve or explicitly link that account first.';
                } elseif ($owners !== []) {
                    try {
                        $provider->verifyAddonDomain($website->hostingServer, $website->hostingAccount, $domain);
                    } catch (\RuntimeException $exception) {
                        $issues[] = $exception->getMessage();
                    }
                }
            }
        }

        return response()->json(['data' => ['ready' => $issues === [], 'issues' => $issues, 'development_domain' => $website->development_domain ?: $website->domain, 'expected_ip' => $website->hostingAccount?->assigned_ip]]);
    }

    public function store(Request $request, Website $website, HostingProviderManager $providers)
    {
        $data = $request->validate([
            'production_domain' => ['required', 'string', 'max:253', 'regex:/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/i'],
            'enable_indexing' => ['sometimes', 'boolean'], 'dns_override' => ['sometimes', 'boolean'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);
        $domain = strtolower(rtrim(trim($data['production_domain']), '.'));
        $website->load(['hostingServer', 'hostingAccount']);
        if ($website->environment !== 'development') throw ValidationException::withMessages(['website' => ['Only a development website can be launched.']]);
        if (! $website->hostingServer || ! $website->hostingAccount || $website->hostingAccount->provider_missing) throw ValidationException::withMessages(['hosting' => ['A live, verified Krystal hosting account is required.']]);
        if (! $website->hostingAccount->automation_password_encrypted) throw ValidationException::withMessages(['hosting' => ['Automation credentials are not available for this development account.']]);
        $provider = $providers->for($website->hostingServer);
        if (! $provider instanceof KrystalWhmProvider) throw ValidationException::withMessages(['hosting' => ['Go Live currently requires a Krystal WHM connection.']]);

        $conflict = Website::whereKeyNot($website->id)->where(function ($query) use ($domain) {
            $query->where('domain', $domain)->orWhere('current_domain', $domain)->orWhere('development_domain', $domain)->orWhere('production_domain', $domain);
        })->exists();
        if ($conflict) throw ValidationException::withMessages(['production_domain' => ['This domain is already used by another CRM website.']]);
        $owners = $provider->domainOwners($website->hostingServer, $domain);
        if ($owners !== []) {
            if (count($owners) !== 1 || strtolower((string) ($owners[0]['username'] ?? '')) !== strtolower($website->hostingAccount->username)) {
                throw ValidationException::withMessages(['production_domain' => ['This domain already exists on Krystal. Resolve or explicitly link that account first.']]);
            }
            try {
                $provider->verifyAddonDomain($website->hostingServer, $website->hostingAccount, $domain);
            } catch (\RuntimeException $exception) {
                throw ValidationException::withMessages(['production_domain' => [$exception->getMessage()]]);
            }
        }

        $run = DB::transaction(function () use ($website, $domain, $data, $request) {
            if ($existing = WebsiteLaunchRun::where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) return $existing;
            if (WebsiteLaunchRun::where('website_id', $website->id)->whereNotIn('state', ['complete', 'failed'])->exists()) throw ValidationException::withMessages(['website' => ['This website already has a launch in progress.']]);
            $website->update(['production_domain' => $domain]);
            $run = WebsiteLaunchRun::create([
                'public_id' => (string) Str::uuid(), 'website_id' => $website->id, 'hosting_account_id' => $website->hosting_account_id,
                'initiated_by_user_id' => $request->user()->id, 'idempotency_key' => $data['idempotency_key'],
                'development_domain' => $website->development_domain ?: $website->domain, 'production_domain' => $domain,
                'options' => ['enable_indexing' => (bool) ($data['enable_indexing'] ?? false), 'dns_override' => (bool) ($data['dns_override'] ?? false)],
            ]);
            foreach (['preflight', 'attach_production_domain', 'verify_production_domain', 'check_dns', 'trigger_autossl', 'check_ssl', 'migrate_wordpress', 'verify_production', 'redirect_development_domain'] as $step) $run->steps()->create(['step' => $step]);
            return $run;
        });

        if ($run->state === 'pending') ProcessWebsiteLaunch::dispatch($run->id)->afterCommit();
        return response()->json(['data' => $this->present($run)], 201);
    }

    public function show(WebsiteLaunchRun $websiteLaunchRun)
    {
        return response()->json(['data' => $this->present($websiteLaunchRun)]);
    }

    public function retry(Request $request, WebsiteLaunchRun $websiteLaunchRun)
    {
        $data = $request->validate(['dns_override' => ['sometimes', 'boolean']]);
        if (! in_array($websiteLaunchRun->state, ['failed', 'waiting_for_dns', 'waiting_for_ssl'], true)) throw ValidationException::withMessages(['state' => ['Only an incomplete launch can be checked or retried.']]);
        if (array_key_exists('dns_override', $data)) $websiteLaunchRun->update(['options' => [...($websiteLaunchRun->options ?? []), 'dns_override' => (bool) $data['dns_override']]]);
        $websiteLaunchRun->steps()->whereIn('status', ['failed', 'waiting'])->update(['status' => 'pending', 'safe_message' => null, 'completed_at' => null]);
        $websiteLaunchRun->update(['state' => 'pending', 'failed_step' => null, 'safe_error' => null, 'next_check_at' => null, 'completed_at' => null]);
        ProcessWebsiteLaunch::dispatch($websiteLaunchRun->id);
        return response()->json(['data' => $this->present($websiteLaunchRun)], 202);
    }

    private function present(WebsiteLaunchRun $run): array
    {
        $run->loadMissing(['website:id,name,domain,development_domain,production_domain,current_domain,environment', 'account:id,username,primary_domain,assigned_ip,status', 'steps']);
        $steps = $run->steps->map(function ($step) {
            $data = $step->toArray();
            $data['step'] = match ($data['step']) {
                'change_primary_domain' => 'attach_production_domain',
                'reconcile_account' => 'verify_production_domain',
                default => $data['step'],
            };
            return $data;
        })->values();
        return ['id' => $run->id, 'public_id' => $run->public_id, 'state' => $run->state, 'development_domain' => $run->development_domain, 'production_domain' => $run->production_domain, 'expected_ip' => $run->expected_ip, 'dns_status' => $run->dns_status, 'ssl_status' => $run->ssl_status, 'safe_error' => $run->safe_error, 'next_check_at' => $run->next_check_at, 'website' => $run->website, 'account' => $run->account, 'steps' => $steps];
    }
}
