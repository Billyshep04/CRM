<?php

namespace App\Services\Analytics;

use App\Models\Website;
use App\Models\WebsiteAnalyticsSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns stored snapshots into the payload the CRM and portal render. Never
 * calls Google — everything here is derived from the local tables.
 */
class WebsiteAnalyticsReportBuilder
{
    private const RANGES = [
        '28d' => ['days' => 28, 'label' => 'Last 28 days'],
        '90d' => ['days' => 90, 'label' => 'Last 90 days'],
        'month' => ['label' => 'This month'],
        'last_month' => ['label' => 'Last month'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(Website $website, string $range = '28d', bool $withBreakdowns = true): array
    {
        $range = array_key_exists($range, self::RANGES) ? $range : '28d';
        [$start, $end] = $this->resolveRange($range);
        $length = $start->diffInDays($end) + 1;
        [$prevStart, $prevEnd] = [$start->copy()->subDays($length), $start->copy()->subDay()];

        if (! $website->analyticsConfigured() && $website->google_analytics_status === null) {
            return ['status' => 'unconfigured', 'property_id' => null];
        }

        $daily = $this->dailySnapshots($website, $prevStart, $end);
        $current = $daily->filter(fn ($s) => $this->within($s, $start, $end));
        $previous = $daily->filter(fn ($s) => $this->within($s, $prevStart, $prevEnd));

        $totals = $this->sum($current);
        $previousTotals = $this->sum($previous);

        $payload = [
            'status' => $website->google_analytics_status ?? ($website->analyticsConfigured() ? 'connected' : 'unconfigured'),
            'property_id' => $website->google_analytics_property_id,
            'dashboard_url' => $website->google_analytics_dashboard_url,
            'last_synced_at' => $website->google_analytics_last_synced_at,
            'last_error' => $website->google_analytics_last_error,
            'range' => ['key' => $range, 'label' => self::RANGES[$range]['label'], 'start' => $start->toDateString(), 'end' => $end->toDateString()],
            'totals' => $totals,
            'previous_totals' => $previousTotals,
            'deltas' => $this->deltas($totals, $previousTotals),
            'series' => $current->map(fn ($s) => [
                'date' => $s->period_start,
                'sessions' => $s->sessions,
                'total_users' => $s->total_users,
                'screen_page_views' => $s->screen_page_views,
                'conversions' => $s->conversions,
            ])->values()->all(),
            'secondary' => $this->secondary($website),
        ];

        if ($withBreakdowns) {
            $months = $this->monthSnapshots($website, $start, $end);
            $payload['breakdowns'] = $this->breakdowns($months);
            $payload['key_events'] = $this->keyEvents($months);
        }

        return $payload;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(string $range): array
    {
        $yesterday = Carbon::yesterday();

        return match ($range) {
            'month' => [$yesterday->copy()->startOfMonth(), $yesterday->copy()],
            'last_month' => [
                $yesterday->copy()->subMonthNoOverflow()->startOfMonth(),
                $yesterday->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            default => [$yesterday->copy()->subDays(self::RANGES[$range]['days'] - 1), $yesterday->copy()],
        };
    }

    /**
     * @return Collection<int, WebsiteAnalyticsSnapshot>
     */
    private function dailySnapshots(Website $website, Carbon $start, Carbon $end): Collection
    {
        return $website->analyticsSnapshots()
            ->where('granularity', 'day')
            ->whereBetween('period_start', [$start->toDateString(), $end->toDateString()])
            ->orderBy('period_start')
            ->get();
    }

    /**
     * @return Collection<int, WebsiteAnalyticsSnapshot>
     */
    private function monthSnapshots(Website $website, Carbon $start, Carbon $end): Collection
    {
        return $website->analyticsSnapshots()
            ->where('granularity', 'month')
            ->where('period_start', '<=', $end->toDateString())
            ->where('period_end', '>=', $start->toDateString())
            ->with('dimensionRows')
            ->orderBy('period_start')
            ->get();
    }

    private function within(WebsiteAnalyticsSnapshot $snapshot, Carbon $start, Carbon $end): bool
    {
        return $snapshot->period_start >= $start->toDateString()
            && $snapshot->period_start <= $end->toDateString();
    }

    /**
     * @param  Collection<int, WebsiteAnalyticsSnapshot>  $snapshots
     * @return array<string, int>
     */
    private function sum(Collection $snapshots): array
    {
        return [
            'sessions' => (int) $snapshots->sum('sessions'),
            'total_users' => (int) $snapshots->sum('total_users'),
            'screen_page_views' => (int) $snapshots->sum('screen_page_views'),
            'conversions' => (int) $snapshots->sum('conversions'),
        ];
    }

    /**
     * @param  array<string, int>  $current
     * @param  array<string, int>  $previous
     * @return array<string, array{value: int, percent: ?float}>
     */
    private function deltas(array $current, array $previous): array
    {
        $deltas = [];
        foreach ($current as $metric => $value) {
            $was = $previous[$metric] ?? 0;
            $deltas[$metric] = [
                'value' => $value - $was,
                'percent' => $was > 0 ? round((($value - $was) / $was) * 100, 1) : null,
            ];
        }

        return $deltas;
    }

    /**
     * Rich engagement metrics from the most recent month snapshot.
     *
     * @return array<string, mixed>|null
     */
    private function secondary(Website $website): ?array
    {
        $month = $website->analyticsSnapshots()->where('granularity', 'month')->orderByDesc('period_start')->first();
        if (! $month) {
            return null;
        }

        return [
            'period_start' => $month->period_start,
            'period_end' => $month->period_end,
            'new_users' => $month->new_users,
            'engaged_sessions' => $month->engaged_sessions,
            'engagement_rate' => $month->engagement_rate,
            'bounce_rate' => $month->bounce_rate,
            'avg_engagement_time_seconds' => $month->avg_engagement_time_seconds,
        ];
    }

    /**
     * @param  Collection<int, WebsiteAnalyticsSnapshot>  $months
     * @return array<string, list<array<string, mixed>>>
     */
    private function breakdowns(Collection $months): array
    {
        $rows = $months->flatMap(fn ($m) => $m->dimensionRows);

        return $rows->groupBy('dimension')->map(function (Collection $group) {
            return $group->groupBy('dimension_value')->map(function (Collection $entries, string $value) {
                return [
                    'value' => $value,
                    'sessions' => (int) $entries->sum('sessions'),
                    'total_users' => (int) $entries->sum('total_users'),
                    'screen_page_views' => (int) $entries->sum('screen_page_views'),
                    'conversions' => (int) $entries->sum('conversions'),
                ];
            })->sortByDesc('sessions')->take(10)->values()->all();
        })->all();
    }

    /**
     * @param  Collection<int, WebsiteAnalyticsSnapshot>  $months
     * @return array<string, int>
     */
    private function keyEvents(Collection $months): array
    {
        $events = [];
        foreach ($months as $month) {
            foreach ($month->key_events ?? [] as $name => $count) {
                $events[$name] = ($events[$name] ?? 0) + (int) $count;
            }
        }
        arsort($events);

        return $events;
    }
}
