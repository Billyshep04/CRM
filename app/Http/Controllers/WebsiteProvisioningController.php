<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebsiteProvisioning;
use App\Models\HostingPackage;
use App\Models\HostingServer;
use App\Models\Website;
use App\Models\WebsiteProvisioningRun;
use App\Models\WordpressProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WebsiteProvisioningController extends Controller
{
    public function options()
    {
        $mode = config('hosting.provisioning_mode', 'mock');
        $available = $mode === 'live'
            ? config('hosting.allow_live_provisioning', false)
            : app()->environment(['local', 'testing']);

        return response()->json(['data' => [
            'mode' => $mode,
            'live_enabled' => config('hosting.allow_live_provisioning', false),
            'provisioning_available' => $available,
            'blocking_reason' => $available ? null : ($mode === 'mock'
                ? 'Preview provisioning is disabled on production. Enable live provisioning before creating a website.'
                : 'Live hosting provisioning is disabled.'),
            'servers' => HostingServer::with(['packages' => fn ($query) => $query->where('active', true)])->where('status', 'active')->get()->map(fn ($server) => [
                'id' => $server->id, 'name' => $server->name, 'provider' => $server->provider, 'api_type' => $server->api_type,
                'credential_username' => $server->credentials['username'] ?? null, 'has_token' => ! empty($server->credentials['token']),
                'packages' => $server->packages->map(fn ($package) => ['id' => $package->id, 'name' => $package->name, 'shell_access' => $package->shell_access, 'limits' => $package->limits]),
            ]),
            'profiles' => WordpressProfile::where('active', true)->get(),
        ]]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'], 'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/i'],
            'environment' => ['required', Rule::in(['production', 'development', 'staging'])],
            'hosting_server_id' => ['required', 'exists:hosting_servers,id'], 'hosting_package_id' => ['required', 'exists:hosting_packages,id'],
            'wordpress_profile_id' => ['nullable', 'exists:wordpress_profiles,id'], 'website_type' => ['required', Rule::in(['wordpress', 'blank'])],
            'options' => ['sometimes', 'array'], 'options.site_title' => ['nullable', 'string', 'max:200'],
            'options.admin_username' => ['nullable', 'string', 'min:4', 'max:60', 'regex:/^[a-zA-Z0-9_.-]+$/', 'not_in:admin,administrator'],
            'options.admin_email' => ['nullable', 'email:rfc', 'max:255'], 'options.discourage_search_engines' => ['nullable', 'boolean'],
            'options.install_agent' => ['nullable', 'boolean'], 'idempotency_key' => ['required', 'string', 'max:100'],
        ]);
        $mode = config('hosting.provisioning_mode', 'mock');
        if ($mode === 'mock' && ! app()->environment(['local', 'testing'])) {
            throw ValidationException::withMessages(['provisioning' => ['Preview provisioning cannot create hosting accounts on production. Set HOSTING_PROVISIONING_MODE=live and explicitly enable live provisioning.']]);
        }
        if ($mode === 'live' && ! config('hosting.allow_live_provisioning')) throw ValidationException::withMessages(['provisioning' => ['Live hosting provisioning is disabled.']]);
        $data['domain'] = strtolower(rtrim(trim($data['domain']), '.'));

        $package = HostingPackage::whereKey($data['hosting_package_id'])->where('hosting_server_id', $data['hosting_server_id'])->first();
        if (! $package) throw ValidationException::withMessages(['hosting_package_id' => ['The selected package does not belong to the selected hosting server.']]);
        $server = HostingServer::whereKey($data['hosting_server_id'])->where('status', 'active')->firstOrFail();
        if ($mode === 'live' && $server->api_type !== 'whm') throw ValidationException::withMessages(['hosting_server_id' => ['Live Krystal provisioning requires a WHM hosting connection.']]);
        if ($data['website_type'] === 'wordpress') {
            $data['options']['site_title'] ??= $data['name'];
            $data['options']['admin_username'] ??= config('hosting.wordpress_admin_username', 'webstamp_admin');
            $data['options']['admin_email'] ??= config('hosting.wordpress_admin_email') ?: $request->user()->email;
        }

        $run = DB::transaction(function () use ($data, $request) {
            if ($existing = WebsiteProvisioningRun::where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) return $existing;
            $activeProvisioningExists = WebsiteProvisioningRun::where('domain', $data['domain'])
                ->whereNotIn('state', ['complete', 'failed'])
                ->where(function ($query) {
                    $query->whereNull('website_id')->orWhereHas('website');
                })
                ->exists();
            if (Website::where('domain', $data['domain'])->exists() || $activeProvisioningExists) throw ValidationException::withMessages(['domain' => ['This domain already exists or is already being provisioned.']]);

            $token = Str::random(64);
            $website = Website::create(['customer_id' => $data['customer_id'], 'hosting_server_id' => $data['hosting_server_id'], 'name' => $data['name'], 'domain' => $data['domain'], 'login_url' => 'https://'.$data['domain'].'/wp-admin/', 'environment' => $data['environment'], 'wordpress_enabled' => $data['website_type'] === 'wordpress', 'management_enabled' => true, 'hosting_enabled' => true, 'provisioning_status' => 'pending', 'status' => 'unknown', 'portal_visibility' => Website::defaultPortalVisibility(), 'agent_token_hash' => hash('sha256', $token), 'agent_token_encrypted' => $token]);
            $run = WebsiteProvisioningRun::create(['public_id' => (string) Str::uuid(), 'website_id' => $website->id, 'hosting_server_id' => $data['hosting_server_id'], 'hosting_package_id' => $data['hosting_package_id'], 'wordpress_profile_id' => $data['wordpress_profile_id'] ?? null, 'initiated_by_user_id' => $request->user()->id, 'idempotency_key' => $data['idempotency_key'], 'domain' => $data['domain'], 'mode' => config('hosting.provisioning_mode', 'mock'), 'website_type' => $data['website_type'], 'options' => $data['options'] ?? []]);
            $steps = ['validate_prerequisites', 'create_cpanel_account', 'wait_for_cpanel'];
            if ($data['website_type'] === 'wordpress') $steps = [...$steps, 'connect_ssh', 'download_wordpress', 'create_database', 'create_database_user', 'grant_database_privileges', 'create_wp_config', 'install_wordpress', 'configure_wordpress', 'verify_wordpress'];
            $steps = [...$steps, 'check_dns', 'check_ssl'];
            if ($data['website_type'] === 'wordpress') $steps = [...$steps, 'install_agent', 'enable_monitoring'];
            $steps[] = 'run_initial_health_check';
            foreach ($steps as $step) $run->steps()->create(['step' => $step]);
            return $run;
        });

        if ($run->state === 'pending') ProcessWebsiteProvisioning::dispatch($run->id)->afterCommit();
        return response()->json(['data' => $this->present($run->fresh())], 201);
    }

    public function show(WebsiteProvisioningRun $websiteProvisioningRun) { return response()->json(['data' => $this->present($websiteProvisioningRun)]); }

    public function retry(WebsiteProvisioningRun $websiteProvisioningRun)
    {
        if (! in_array($websiteProvisioningRun->state, ['failed', 'action_required', 'waiting_for_dns', 'waiting_for_ssl'], true)) throw ValidationException::withMessages(['state' => ['Only incomplete provisioning runs can be checked or retried.']]);
        if ($websiteProvisioningRun->mode === 'mock' && ! app()->environment(['local', 'testing'])) {
            if (config('hosting.provisioning_mode') !== 'live' || ! config('hosting.allow_live_provisioning')) {
                throw ValidationException::withMessages(['mode' => ['This preview cannot be retried until production is configured for live provisioning.']]);
            }
            $websiteProvisioningRun->steps()->update(['status' => 'pending', 'attempts' => 0, 'safe_message' => null, 'metadata' => null, 'started_at' => null, 'completed_at' => null]);
            $websiteProvisioningRun->website?->update(['provisioning_status' => 'pending', 'lifecycle_state' => 'draft']);
            $websiteProvisioningRun->update(['mode' => 'live', 'state' => 'pending', 'failed_step' => null, 'safe_error' => null, 'expected_ip' => null, 'dns_status' => null, 'ssl_status' => null, 'completed_at' => null, 'next_check_at' => null]);
        } else {
            $websiteProvisioningRun->steps()->whereIn('status', ['failed', 'manual_action', 'waiting'])->update(['status' => 'pending', 'safe_message' => null, 'completed_at' => null]);
            $websiteProvisioningRun->update(['next_check_at' => null]);
        }
        ProcessWebsiteProvisioning::dispatch($websiteProvisioningRun->id);
        return response()->json(['data' => $this->present($websiteProvisioningRun->fresh())], 202);
    }

    private function present(WebsiteProvisioningRun $run): array
    {
        $run->loadMissing(['website:id,name,domain,provisioning_status', 'account:id,username,primary_domain,assigned_ip,status', 'steps:id,website_provisioning_run_id,step,status,attempts,safe_message,metadata,started_at,completed_at']);
        return ['id' => $run->id, 'public_id' => $run->public_id, 'state' => $run->state, 'mode' => $run->mode, 'website_type' => $run->website_type, 'domain' => $run->domain, 'expected_ip' => $run->expected_ip, 'dns_provider' => $run->dns_provider, 'dns_status' => $run->dns_status, 'ssl_status' => $run->ssl_status, 'next_check_at' => $run->next_check_at, 'safe_error' => $run->safe_error, 'website' => $run->website, 'account' => $run->account, 'steps' => $run->steps];
    }
}
