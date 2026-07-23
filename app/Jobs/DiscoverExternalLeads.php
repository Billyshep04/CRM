<?php

namespace App\Jobs;

use App\Actions\WebsiteAudits\StartWebsiteAudit;
use App\Contracts\LeadDiscoveryProvider;
use App\Models\Business;
use App\Models\LeadDiscoveryRun;
use App\Models\Website;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class DiscoverExternalLeads implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $runId)
    {
        $this->onQueue((string) config('lead-discovery.queue', 'discovery'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('lead-discovery:'.$this->runId))->expireAfter(210)];
    }

    public function handle(LeadDiscoveryProvider $provider, StartWebsiteAudit $audits): void
    {
        $run = LeadDiscoveryRun::withTrashed()->findOrFail($this->runId);
        if ($run->trashed()) {
            return;
        }
        if ($run->status === 'completed') {
            return;
        }
        $run->update(['status' => 'running', 'started_at' => $run->started_at ?? now(), 'failure_message' => null]);

        $created = $updated = $skipped = $found = 0;
        $token = null;
        do {
            $remaining = $run->requested_limit - $found;
            $result = $provider->search($run->query, $run->location, min(20, $remaining), $token);
            foreach ($result['places'] as $place) {
                if ($found >= $run->requested_limit) {
                    break 2;
                }
                $found++;
                $domain = $this->domain($place['websiteUri'] ?? null);
                if ($domain && Website::query()->where('login_url', 'like', '%'.$domain.'%')->exists()) {
                    $skipped++;

                    continue;
                }
                $business = null;
                if (($place['id'] ?? null) || $domain) {
                    $business = Business::withTrashed()->where(function ($query) use ($place, $domain): void {
                        if ($place['id'] ?? null) {
                            $query->where('google_place_id', $place['id']);
                        }
                        if ($domain) {
                            $query->orWhere('normalized_domain', $domain);
                        }
                    })->first();
                }
                $data = $this->map($place, $domain, $run);
                if ($business) {
                    if ($business->trashed()) {
                        $business->restore();
                    }
                    $business->update($data + ['last_discovered_at' => now()]);
                    $updated++;
                } else {
                    $business = Business::query()->create($data + [
                        'public_id' => (string) Str::ulid(), 'owner_user_id' => $run->requested_by_user_id,
                        'discovered_at' => now(), 'last_discovered_at' => now(),
                    ]);
                    $created++;
                }
                if ($run->auto_audit && $business->website_url && ! $business->websiteAudits()->whereIn('status', ['pending', 'running'])->exists()) {
                    $audits->execute($business->website_url, null, $business->id, $run->requested_by_user_id);
                }
            }
            $token = $result['next_page_token'];
        } while ($token && $found < $run->requested_limit);

        $run->update(['status' => 'completed', 'results_found' => $found, 'leads_created' => $created, 'leads_updated' => $updated, 'duplicates_skipped' => $skipped, 'completed_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        LeadDiscoveryRun::query()->whereKey($this->runId)->update(['status' => 'failed', 'failed_at' => now(), 'failure_message' => mb_substr($exception?->getMessage() ?? 'Discovery failed.', 0, 2000)]);
    }

    private function map(array $place, ?string $domain, LeadDiscoveryRun $run): array
    {
        return [
            'lead_discovery_run_id' => $run->id, 'name' => data_get($place, 'displayName.text', 'Unnamed business'),
            'status' => 'new', 'source' => 'google_places', 'website_url' => $place['websiteUri'] ?? null,
            'normalized_domain' => $domain, 'phone' => $place['nationalPhoneNumber'] ?? null,
            'address' => $place['formattedAddress'] ?? null, 'google_place_id' => $place['id'] ?? null,
            'google_maps_url' => $place['googleMapsUri'] ?? null, 'primary_category' => $place['primaryType'] ?? null,
            'latitude' => data_get($place, 'location.latitude'), 'longitude' => data_get($place, 'location.longitude'),
            'google_rating' => $place['rating'] ?? null, 'google_review_count' => $place['userRatingCount'] ?? null,
            'metadata' => ['discovery_query' => $run->query, 'discovery_location' => $run->location],
        ];
    }

    private function domain(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host ? preg_replace('/^www\./', '', $host) : null;
    }
}
