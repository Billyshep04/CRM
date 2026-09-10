<?php

namespace App\Services\Analytics;

use App\Contracts\AnalyticsProvider;
use App\Models\Website;
use App\Models\WebsiteActivity;
use App\Models\WebsiteAnalyticsSnapshot;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class WebsiteAnalyticsSync
{
    public function __construct(private readonly AnalyticsProvider $provider) {}

    /**
     * First-time (or forced) load: daily rows for the configured window plus a
     * breakdown snapshot for every calendar month it touches.
     */
    public function backfill(Website $website): void
    {
        $days = max(1, (int) config('analytics.backfill_days', 365));
        $this->run($website, Carbon::yesterday()->subDays($days - 1), Carbon::yesterday());
    }

    /**
     * Scheduled refresh: re-pull the trailing window and the current + previous
     * month so late-arriving GA4 data is picked up.
     */
    public function syncRecent(Website $website): void
    {
        $window = max(1, (int) config('analytics.refetch_window_days', 3));
        $this->run($website, Carbon::yesterday()->subDays($window - 1), Carbon::yesterday());
    }

    /**
     * Point a website at a GA4 property and confirm the credentials can read it.
     */
    public function connect(Website $website, string $propertyId): bool
    {
        $propertyId = trim(str_replace('properties/', '', $propertyId));
        $hasAccess = $this->provider->verifyAccess($propertyId);

        $website->forceFill([
            'google_analytics_property_id' => $propertyId,
            'google_analytics_enabled' => $hasAccess,
            'google_analytics_status' => $hasAccess ? 'connected' : 'no_access',
            'google_analytics_last_error' => $hasAccess ? null : 'The analytics service account cannot read this property. Add it as a Viewer in GA4.',
        ])->save();

        if ($hasAccess) {
            $this->logActivity($website, 'Google Analytics connected', "Property {$propertyId} linked for reporting.");
        }

        return $hasAccess;
    }

    private function run(Website $website, Carbon $start, Carbon $end): void
    {
        $propertyId = trim((string) $website->google_analytics_property_id);
        if ($propertyId === '') {
            return;
        }

        try {
            $this->fillDailyRange($website, $propertyId, $start, $end);

            $month = $start->copy()->startOfMonth();
            $lastMonth = $end->copy()->startOfMonth();
            while ($month->lte($lastMonth)) {
                $this->fillMonth($website, $propertyId, $month->copy());
                $month->addMonth();
            }

            $wasConnected = $website->google_analytics_status === 'connected';
            $website->forceFill([
                'google_analytics_status' => 'connected',
                'google_analytics_last_synced_at' => now(),
                'google_analytics_last_error' => null,
            ])->save();

            if (! $wasConnected) {
                $this->logActivity($website, 'Google Analytics reporting active', 'Traffic data is now syncing for this website.');
            }
        } catch (RequestException $exception) {
            $status = in_array($exception->response->status(), [401, 403, 404], true) ? 'no_access' : 'error';
            $this->recordFailure($website, $status, $this->friendlyError($exception, $status));

            if ($status === 'no_access') {
                return; // Nothing to retry — the property needs to be shared with the service account.
            }
            throw $exception;
        } catch (Throwable $exception) {
            $this->recordFailure($website, 'error', mb_substr($exception->getMessage(), 0, 480));
            throw $exception;
        }
    }

    private function fillDailyRange(Website $website, string $propertyId, Carbon $start, Carbon $end): void
    {
        $report = $this->provider->fetchReport($propertyId, $start->toDateString(), $end->toDateString(), ['series']);

        foreach ($report->series as $row) {
            WebsiteAnalyticsSnapshot::query()->updateOrCreate(
                ['website_id' => $website->id, 'granularity' => 'day', 'period_start' => $row['date']],
                [
                    'property_id' => $propertyId,
                    'period_end' => $row['date'],
                    'sessions' => $row['sessions'],
                    'total_users' => $row['total_users'],
                    'screen_page_views' => $row['screen_page_views'],
                    'conversions' => $row['conversions'],
                    'source_data' => $row,
                    'fetched_at' => now(),
                ],
            );
        }
    }

    private function fillMonth(Website $website, string $propertyId, Carbon $month): void
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // Never ask GA4 for days that have not happened yet.
        if ($end->greaterThan(Carbon::yesterday())) {
            $end = Carbon::yesterday();
        }
        if ($end->lessThan($start)) {
            return;
        }

        $report = $this->provider->fetchReport($propertyId, $start->toDateString(), $end->toDateString());
        $totals = $report->totals;

        $snapshot = WebsiteAnalyticsSnapshot::query()->updateOrCreate(
            ['website_id' => $website->id, 'granularity' => 'month', 'period_start' => $start->toDateString()],
            [
                'property_id' => $propertyId,
                'period_end' => $end->toDateString(),
                'sessions' => (int) ($totals['sessions'] ?? 0),
                'total_users' => (int) ($totals['total_users'] ?? 0),
                'new_users' => (int) ($totals['new_users'] ?? 0),
                'engaged_sessions' => (int) ($totals['engaged_sessions'] ?? 0),
                'screen_page_views' => (int) ($totals['screen_page_views'] ?? 0),
                'avg_engagement_time_seconds' => $this->avgEngagement($totals),
                'engagement_rate' => (float) ($totals['engagement_rate'] ?? 0),
                'bounce_rate' => (float) ($totals['bounce_rate'] ?? 0),
                'event_count' => (int) ($totals['event_count'] ?? 0),
                'conversions' => (int) ($totals['key_events'] ?? $totals['conversions'] ?? 0),
                'key_events' => $report->keyEvents,
                'source_data' => $report->raw,
                'fetched_at' => now(),
            ],
        );

        $rows = [];
        foreach ($report->dimensions as $dimension => $entries) {
            foreach ($entries as $entry) {
                $rows[] = [
                    'website_analytics_snapshot_id' => $snapshot->id,
                    'dimension' => $dimension,
                    'dimension_value' => mb_substr((string) $entry['value'], 0, 512),
                    'sessions' => $entry['sessions'],
                    'total_users' => $entry['total_users'],
                    'screen_page_views' => $entry['screen_page_views'],
                    'conversions' => $entry['conversions'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::transaction(function () use ($snapshot, $rows): void {
            $snapshot->dimensionRows()->delete();
            if ($rows !== []) {
                $snapshot->dimensionRows()->getModel()->newQuery()->insert($rows);
            }
        });
    }

    /**
     * @param  array<string, float|int>  $totals
     */
    private function avgEngagement(array $totals): int
    {
        $sessions = (int) ($totals['sessions'] ?? 0);
        $duration = (float) ($totals['user_engagement_duration'] ?? 0);

        return $sessions > 0 ? (int) round($duration / $sessions) : 0;
    }

    private function recordFailure(Website $website, string $status, string $message): void
    {
        $website->forceFill([
            'google_analytics_status' => $status,
            'google_analytics_last_error' => $message,
        ])->save();
    }

    private function friendlyError(RequestException $exception, string $status): string
    {
        if ($status === 'no_access') {
            return 'The analytics service account cannot read this GA4 property. Add its email as a Viewer in the property\'s access management.';
        }

        return 'Google Analytics request failed ('.$exception->response->status().').';
    }

    private function logActivity(Website $website, string $title, string $description): void
    {
        WebsiteActivity::query()->create([
            'website_id' => $website->id,
            'type' => 'analytics',
            'title' => $title,
            'description' => $description,
            'visible_to_customer' => false,
            'performed_at' => now(),
        ]);
    }
}
