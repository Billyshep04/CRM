<?php

namespace App\Http\Controllers;

use App\Http\Resources\WebsiteResource;
use App\Models\Website;
use App\Models\WebsiteActivity;
use App\Services\Websites\WebsiteMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebsiteController extends Controller
{
    public function index(Request $request)
    {
        $query = Website::query()->with(['customer', 'hostingServer', 'hostingAccount', 'subscription', 'latestHealthCheck'])->latest();
        $connection = $request->query('connection', 'linked');
        if ($connection === 'unlinked') $query->whereNull('agent_last_seen_at');
        elseif ($connection === 'linked') $query->whereNotNull('agent_last_seen_at');
        foreach (['customer_id', 'status', 'hosting_enabled', 'management_enabled'] as $filter) {
            if ($request->filled($filter)) $query->where($filter, $request->input($filter));
        }
        if ($request->boolean('needs_attention')) $query->whereIn('status', ['attention', 'critical']);
        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('domain', 'like', "%{$search}%"));
        }
        return WebsiteResource::collection($query->paginate($request->integer('per_page', 25)));
    }

    public function summary()
    {
        $base = Website::query();
        return response()->json(['data' => [
            'total' => (clone $base)->count(),
            'healthy' => (clone $base)->where('status', 'healthy')->count(),
            'needs_attention' => (clone $base)->whereIn('status', ['attention', 'critical'])->count(),
            'offline' => (clone $base)->whereHas('latestHealthCheck', fn ($q) => $q->where('uptime_status', 'offline'))->count(),
            'updates_available' => (clone $base)->whereHas('latestHealthCheck', fn ($q) => $q->where(fn ($i) => $i->where('plugin_updates', '>', 0)->orWhere('theme_updates', '>', 0)))->count(),
            'linked' => (clone $base)->whereNotNull('agent_last_seen_at')->count(),
            'unlinked' => (clone $base)->whereNull('agent_last_seen_at')->count(),
        ]]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $token = Str::random(64);
        $website = Website::create([...$data, 'domain' => $data['domain'] ?? $this->domain($data['login_url']), 'portal_visibility' => $data['portal_visibility'] ?? Website::defaultPortalVisibility(), 'agent_token_hash' => hash('sha256', $token), 'agent_token_encrypted' => $token]);
        WebsiteActivity::create(['website_id' => $website->id, 'created_by_user_id' => $request->user()?->id, 'type' => 'website_created', 'title' => 'Website added', 'performed_at' => now()]);
        return (new WebsiteResource($website->load(['customer', 'hostingServer', 'subscription', 'latestHealthCheck'])))->additional(['agent_token' => $token]);
    }

    public function show(Website $website)
    {
        return new WebsiteResource($website->load(['customer', 'hostingServer', 'hostingAccount', 'subscription', 'latestHealthCheck', 'healthChecks' => fn ($q) => $q->limit(100), 'incidents' => fn ($q) => $q->limit(100), 'activities' => fn ($q) => $q->limit(100), 'provisioningRuns.steps']));
    }

    public function update(Request $request, Website $website)
    {
        $data = $this->validated($request, true);
        if (isset($data['login_url']) && !array_key_exists('domain', $data)) $data['domain'] = $this->domain($data['login_url']);
        $website->update($data);
        WebsiteActivity::create(['website_id' => $website->id, 'created_by_user_id' => $request->user()?->id, 'type' => 'website_updated', 'title' => 'Website details updated', 'performed_at' => now()]);
        return new WebsiteResource($website->fresh()->load(['customer', 'hostingServer', 'subscription', 'latestHealthCheck']));
    }

    public function destroy(Website $website)
    {
        $website->delete();
        return response()->json(['message' => 'Website deleted.']);
    }

    public function check(Website $website, WebsiteMonitor $monitor)
    {
        $check = $monitor->check($website, 'manual');
        return response()->json(['data' => $check]);
    }

    public function regenerateToken(Website $website)
    {
        $token = Str::random(64);
        $website->update(['agent_token_hash' => hash('sha256', $token), 'agent_token_encrypted' => $token]);
        return response()->json(['data' => ['agent_token' => $token], 'message' => 'The old agent token is no longer valid.']);
    }

    public function activity(Request $request, Website $website)
    {
        $data = $request->validate(['type' => ['required', 'string', 'max:80'], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'performed_at' => ['nullable', 'date'], 'visible_to_customer' => ['sometimes', 'boolean']]);
        $activity = WebsiteActivity::create([...$data, 'website_id' => $website->id, 'created_by_user_id' => $request->user()?->id, 'performed_at' => $data['performed_at'] ?? now(), 'visible_to_customer' => $data['visible_to_customer'] ?? true]);
        return response()->json(['data' => $activity], 201);
    }

    private function validated(Request $request, bool $sometimes = false): array
    {
        $required = $sometimes ? 'sometimes' : 'required';
        return $request->validate([
            'customer_id' => [$required, 'integer', 'exists:customers,id'], 'hosting_server_id' => ['nullable', 'integer', 'exists:hosting_servers,id'], 'hosting_account_id' => ['nullable', 'integer', 'exists:hosting_accounts,id'], 'subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
            'name' => [$required, 'string', 'max:255'], 'domain' => ['nullable', 'string', 'max:255'], 'login_url' => [$required, 'url:http,https', 'max:2048'], 'environment' => ['sometimes', Rule::in(['production', 'staging', 'development'])],
            'cpanel_username' => ['nullable', 'string', 'max:255'], 'wordpress_enabled' => ['sometimes', 'boolean'], 'management_enabled' => ['sometimes', 'boolean'], 'hosting_enabled' => ['sometimes', 'boolean'],
            'google_analytics_property_id' => ['nullable', 'string', 'max:255'], 'google_analytics_dashboard_url' => ['nullable', 'url:http,https', 'max:2048'],
            'status' => ['sometimes', Rule::in(['unknown', 'healthy', 'attention', 'critical', 'paused'])], 'notes' => ['nullable', 'string'], 'portal_visibility' => ['sometimes', 'array'], 'portal_visibility.*' => ['boolean'], 'metadata' => ['sometimes', 'nullable', 'array'],
        ]);
    }

    private function domain(string $url): ?string { return parse_url($url, PHP_URL_HOST) ?: null; }
}
