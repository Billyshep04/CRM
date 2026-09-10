<?php

namespace App\Services\Analytics;

use App\Contracts\AnalyticsProvider;
use Illuminate\Support\Carbon;

/**
 * Deterministic sample data for local development and tests. Never makes a
 * network call. Selected when config('analytics.driver') !== 'google'.
 */
class MockAnalyticsProvider implements AnalyticsProvider
{
    public function fetchReport(
        string $propertyId,
        string $startDate,
        string $endDate,
        array $sections = ['series', 'totals', 'dimensions', 'key_events'],
    ): AnalyticsReport {
        $seed = (int) preg_replace('/\D/', '', $propertyId) ?: 42;
        $cursor = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $series = [];
        $sessions = $users = $newUsers = $engaged = $views = $engagementSeconds = $events = $conversions = 0;

        while ($cursor->lte($end)) {
            $n = ($seed + (int) $cursor->format('z') + $cursor->year) % 37;
            $daySessions = 40 + $n * 3;
            $dayUsers = (int) round($daySessions * 0.82);
            $dayViews = $daySessions * 2 + $n;
            $dayConversions = (int) round($daySessions * 0.04);

            $series[] = [
                'date' => $cursor->toDateString(),
                'sessions' => $daySessions,
                'total_users' => $dayUsers,
                'screen_page_views' => $dayViews,
                'conversions' => $dayConversions,
            ];

            $sessions += $daySessions;
            $users += $dayUsers;
            $newUsers += (int) round($dayUsers * 0.65);
            $engaged += (int) round($daySessions * 0.58);
            $views += $dayViews;
            $engagementSeconds += $daySessions * 55;
            $events += $dayViews + $dayConversions;
            $conversions += $dayConversions;

            $cursor->addDay();
        }

        $days = max(1, count($series));

        return new AnalyticsReport(
            totals: [
                'sessions' => $sessions,
                'total_users' => $users,
                'new_users' => $newUsers,
                'engaged_sessions' => $engaged,
                'screen_page_views' => $views,
                'user_engagement_duration' => $engagementSeconds,
                'engagement_rate' => $sessions > 0 ? round($engaged / $sessions, 4) : 0.0,
                'bounce_rate' => $sessions > 0 ? round(1 - ($engaged / $sessions), 4) : 0.0,
                'event_count' => $events,
                'key_events' => $conversions,
            ],
            series: $series,
            dimensions: [
                'page_path' => $this->breakdown(['/', '/about', '/services', '/contact', '/blog'], $sessions),
                'session_default_channel_group' => $this->breakdown(['Organic Search', 'Direct', 'Referral', 'Organic Social', 'Email'], $sessions),
                'country' => $this->breakdown(['United Kingdom', 'United States', 'Ireland', 'Germany', 'France'], $sessions),
                'device_category' => $this->breakdown(['mobile', 'desktop', 'tablet'], $sessions),
            ],
            keyEvents: ['form_submit' => (int) round($conversions * 0.6), 'click_to_call' => (int) round($conversions * 0.4)],
            raw: ['mock' => true, 'property_id' => $propertyId, 'range' => [$startDate, $endDate], 'days' => $days],
        );
    }

    public function verifyAccess(string $propertyId): bool
    {
        return preg_match('/^\d+$/', trim(str_replace('properties/', '', $propertyId))) === 1;
    }

    public function listProperties(): array
    {
        return [
            ['property_id' => '100000001', 'display_name' => 'Sample Property A', 'account_name' => 'Mock Account'],
            ['property_id' => '100000002', 'display_name' => 'Sample Property B', 'account_name' => 'Mock Account'],
        ];
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, sessions: int, total_users: int, screen_page_views: int, conversions: int}>
     */
    private function breakdown(array $values, int $totalSessions): array
    {
        $weights = [0.42, 0.24, 0.16, 0.11, 0.07];
        $rows = [];
        foreach ($values as $index => $value) {
            $share = $weights[$index] ?? 0.05;
            $sessions = (int) round($totalSessions * $share);
            $rows[] = [
                'value' => $value,
                'sessions' => $sessions,
                'total_users' => (int) round($sessions * 0.82),
                'screen_page_views' => $sessions * 2,
                'conversions' => (int) round($sessions * 0.04),
            ];
        }

        return $rows;
    }
}
