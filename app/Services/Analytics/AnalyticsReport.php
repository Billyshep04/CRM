<?php

namespace App\Services\Analytics;

/**
 * Normalised GA4 report for a single date range.
 */
class AnalyticsReport
{
    /**
     * @param  array<string, float|int>  $totals  Period totals keyed by metric name.
     * @param  list<array{date: string, sessions: int, total_users: int, screen_page_views: int, conversions: int}>  $series  Daily rows, ascending by date.
     * @param  array<string, list<array{value: string, sessions: int, total_users: int, screen_page_views: int, conversions: int}>>  $dimensions  Breakdown rows keyed by dimension name.
     * @param  array<string, int>  $keyEvents  Key-event counts keyed by event name.
     * @param  array<string, mixed>  $raw  Raw provider payload, stored for re-derivation.
     */
    public function __construct(
        public readonly array $totals = [],
        public readonly array $series = [],
        public readonly array $dimensions = [],
        public readonly array $keyEvents = [],
        public readonly array $raw = [],
    ) {}

    public function total(string $metric, float|int $default = 0): float|int
    {
        return $this->totals[$metric] ?? $default;
    }
}
