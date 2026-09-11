<?php

namespace App\Services\Analytics;

use App\Exceptions\AnalyticsMisconfiguredException;

/**
 * Resolves config('analytics.driver'). A misspelled, blank-but-not-explicit,
 * or otherwise unrecognised value must never silently fall back to the mock
 * provider — that previously caused fabricated traffic numbers to be shown
 * to staff and customers as if they were real Google Analytics data.
 */
final class AnalyticsDriver
{
    public const GOOGLE = 'google';

    public const MOCK = 'mock';

    /**
     * The active driver, normalised. Throws if the configured value isn't
     * recognised — callers that talk to Google (connect/sync/properties)
     * must let this propagate rather than defaulting to mock.
     */
    public static function current(): string
    {
        $raw = strtolower(trim((string) config('analytics.driver', self::MOCK)));

        if (! in_array($raw, [self::GOOGLE, self::MOCK], true)) {
            throw new AnalyticsMisconfiguredException(
                "ANALYTICS_DRIVER is set to \"{$raw}\", which isn't valid. Expected \"google\" or \"mock\"."
            );
        }

        return $raw;
    }

    public static function isLive(): bool
    {
        return self::current() === self::GOOGLE;
    }

    /**
     * Same as current(), but for read-only status displays that must never
     * throw — returns "misconfigured" instead of blowing up the caller.
     */
    public static function currentOrUnknown(): string
    {
        try {
            return self::current();
        } catch (AnalyticsMisconfiguredException) {
            return 'misconfigured';
        }
    }
}
