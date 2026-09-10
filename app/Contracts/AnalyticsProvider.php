<?php

namespace App\Contracts;

use App\Services\Analytics\AnalyticsReport;

interface AnalyticsProvider
{
    /**
     * Pull a report for the inclusive date range (both dates as Y-m-d).
     *
     * @param  list<string>  $sections  Any of: series, totals, dimensions, key_events.
     *                                   Providers may return more than requested, never less.
     */
    public function fetchReport(
        string $propertyId,
        string $startDate,
        string $endDate,
        array $sections = ['series', 'totals', 'dimensions', 'key_events'],
    ): AnalyticsReport;

    /**
     * Whether the configured credentials can read this property.
     */
    public function verifyAccess(string $propertyId): bool;

    /**
     * GA4 properties the configured credentials can see.
     *
     * @return list<array{property_id: string, display_name: string, account_name: ?string}>
     */
    public function listProperties(): array;
}
