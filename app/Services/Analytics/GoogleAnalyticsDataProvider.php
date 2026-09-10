<?php

namespace App\Services\Analytics;

use App\Contracts\AnalyticsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAnalyticsDataProvider implements AnalyticsProvider
{
    /**
     * GA4 dimension name => the key we persist it under.
     */
    private const DIMENSION_MAP = [
        'pagePath' => 'page_path',
        'sessionDefaultChannelGroup' => 'session_default_channel_group',
        'country' => 'country',
        'deviceCategory' => 'device_category',
    ];

    private const METRICS = [
        'sessions',
        'totalUsers',
        'newUsers',
        'engagedSessions',
        'screenPageViews',
        'userEngagementDuration',
        'engagementRate',
        'bounceRate',
        'eventCount',
        'keyEvents',
    ];

    public function __construct(private readonly GoogleServiceAccountToken $token) {}

    public function fetchReport(
        string $propertyId,
        string $startDate,
        string $endDate,
        array $sections = ['series', 'totals', 'dimensions', 'key_events'],
    ): AnalyticsReport {
        $property = $this->normaliseProperty($propertyId);
        $range = [['startDate' => $startDate, 'endDate' => $endDate]];
        $wantDimensions = in_array('dimensions', $sections, true);
        $wantKeyEvents = in_array('key_events', $sections, true);

        $breakdownMetrics = array_map(static fn (string $m) => ['name' => $m], ['sessions', 'totalUsers', 'screenPageViews', 'keyEvents']);
        $breakdown = static fn (string $dimension): array => [
            'dimensions' => [['name' => $dimension]],
            'metrics' => $breakdownMetrics,
            'dateRanges' => $range,
            'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
            'limit' => 25,
        ];

        $timeRequest = [
            'dimensions' => [['name' => 'date']],
            'metrics' => array_map(static fn (string $m) => ['name' => $m], self::METRICS),
            'dateRanges' => $range,
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
            'limit' => 100000,
        ];

        $batchOne = $this->runBatch($property, array_values(array_filter([
            $timeRequest,
            $wantDimensions ? $breakdown('pagePath') : null,
            $wantDimensions ? $breakdown('sessionDefaultChannelGroup') : null,
        ])));
        [$timeReport, $pagesReport, $channelsReport] = array_pad($batchOne, 3, []);

        $countryReport = $deviceReport = $eventsReport = [];
        $batchTwo = [];
        if ($wantDimensions || $wantKeyEvents) {
            $batchTwo = $this->runBatch($property, array_values(array_filter([
                $wantDimensions ? $breakdown('country') : null,
                $wantDimensions ? $breakdown('deviceCategory') : null,
                $wantKeyEvents ? [
                    'dimensions' => [['name' => 'eventName']],
                    'metrics' => [['name' => 'eventCount'], ['name' => 'keyEvents']],
                    'dateRanges' => $range,
                    'orderBys' => [['metric' => ['metricName' => 'eventCount'], 'desc' => true]],
                    'limit' => 50,
                ] : null,
            ])));

            $offset = 0;
            if ($wantDimensions) {
                [$countryReport, $deviceReport] = array_pad(array_slice($batchTwo, 0, 2), 2, []);
                $offset = 2;
            }
            if ($wantKeyEvents) {
                $eventsReport = $batchTwo[$offset] ?? [];
            }
        }

        return new AnalyticsReport(
            totals: $this->totalsFrom($timeReport),
            series: $this->seriesFrom($timeReport),
            dimensions: $wantDimensions ? [
                self::DIMENSION_MAP['pagePath'] => $this->breakdownFrom($pagesReport),
                self::DIMENSION_MAP['sessionDefaultChannelGroup'] => $this->breakdownFrom($channelsReport),
                self::DIMENSION_MAP['country'] => $this->breakdownFrom($countryReport),
                self::DIMENSION_MAP['deviceCategory'] => $this->breakdownFrom($deviceReport),
            ] : [],
            keyEvents: $wantKeyEvents ? $this->keyEventsFrom($eventsReport) : [],
            raw: ['batch_one' => $batchOne, 'batch_two' => $batchTwo],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $requests
     * @return list<array<string, mixed>>
     */
    private function runBatch(string $property, array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        return $this->dataApi()->post("/{$property}:batchRunReports", ['requests' => $requests])
            ->throw()
            ->json('reports', []);
    }

    public function verifyAccess(string $propertyId): bool
    {
        try {
            $this->dataApi()->post("/{$this->normaliseProperty($propertyId)}:runReport", [
                'metrics' => [['name' => 'sessions']],
                'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'yesterday']],
                'limit' => 1,
            ])->throw();

            return true;
        } catch (RequestException $exception) {
            if (in_array($exception->response->status(), [401, 403, 404], true)) {
                return false;
            }
            throw $exception;
        }
    }

    public function listProperties(): array
    {
        $summaries = $this->adminApi()->get('/accountSummaries', ['pageSize' => 200])
            ->throw()
            ->json('accountSummaries', []);

        $properties = [];
        foreach ($summaries as $account) {
            foreach ($account['propertySummaries'] ?? [] as $property) {
                $id = str_replace('properties/', '', (string) ($property['property'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $properties[] = [
                    'property_id' => $id,
                    'display_name' => (string) ($property['displayName'] ?? $id),
                    'account_name' => $account['displayName'] ?? null,
                ];
            }
        }

        usort($properties, static fn ($a, $b) => strcasecmp($a['display_name'], $b['display_name']));

        return $properties;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, float|int>
     */
    private function totalsFrom(array $report): array
    {
        $headers = array_map(static fn ($h) => $h['name'] ?? '', $report['metricHeaders'] ?? []);
        $values = $report['totals'][0]['metricValues'] ?? [];

        $totals = [];
        foreach ($headers as $index => $name) {
            $totals[$this->snake($name)] = $this->numeric($values[$index]['value'] ?? 0, $name);
        }

        return $totals;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<array{date: string, sessions: int, total_users: int, screen_page_views: int, conversions: int}>
     */
    private function seriesFrom(array $report): array
    {
        $headers = array_map(static fn ($h) => $h['name'] ?? '', $report['metricHeaders'] ?? []);
        $series = [];
        foreach ($report['rows'] ?? [] as $row) {
            $date = $row['dimensionValues'][0]['value'] ?? '';
            $metrics = [];
            foreach ($headers as $index => $name) {
                $metrics[$name] = $this->numeric($row['metricValues'][$index]['value'] ?? 0, $name);
            }
            $series[] = [
                'date' => preg_match('/^\d{8}$/', $date) ? substr($date, 0, 4).'-'.substr($date, 4, 2).'-'.substr($date, 6, 2) : $date,
                'sessions' => (int) ($metrics['sessions'] ?? 0),
                'total_users' => (int) ($metrics['totalUsers'] ?? 0),
                'screen_page_views' => (int) ($metrics['screenPageViews'] ?? 0),
                'conversions' => (int) ($metrics['keyEvents'] ?? 0),
            ];
        }

        usort($series, static fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $series;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<array{value: string, sessions: int, total_users: int, screen_page_views: int, conversions: int}>
     */
    private function breakdownFrom(array $report): array
    {
        $rows = [];
        foreach ($report['rows'] ?? [] as $row) {
            $values = $row['metricValues'] ?? [];
            $rows[] = [
                'value' => (string) ($row['dimensionValues'][0]['value'] ?? '(not set)'),
                'sessions' => (int) ($values[0]['value'] ?? 0),
                'total_users' => (int) ($values[1]['value'] ?? 0),
                'screen_page_views' => (int) ($values[2]['value'] ?? 0),
                'conversions' => (int) ($values[3]['value'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, int>
     */
    private function keyEventsFrom(array $report): array
    {
        $events = [];
        foreach ($report['rows'] ?? [] as $row) {
            $name = (string) ($row['dimensionValues'][0]['value'] ?? '');
            $keyEventCount = (int) ($row['metricValues'][1]['value'] ?? 0);
            if ($name !== '' && $keyEventCount > 0) {
                $events[$name] = $keyEventCount;
            }
        }

        return $events;
    }

    private function dataApi(): PendingRequest
    {
        return $this->client((string) config('analytics.google.data_api_base'));
    }

    private function adminApi(): PendingRequest
    {
        return $this->client((string) config('analytics.google.admin_api_base'));
    }

    private function client(string $baseUrl): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->withToken($this->token->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('analytics.google.timeout', 30))
            ->retry(3, 1500, function (\Throwable $e): bool {
                return $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 504], true));
            }, throw: false);
    }

    private function normaliseProperty(string $propertyId): string
    {
        $id = trim(str_replace('properties/', '', $propertyId));
        if (! preg_match('/^\d+$/', $id)) {
            throw new RuntimeException("Invalid GA4 property id: {$propertyId}");
        }

        return "properties/{$id}";
    }

    private function numeric(mixed $value, string $metric): float|int
    {
        return str_contains(strtolower($metric), 'rate')
            ? round((float) $value, 4)
            : (int) round((float) $value);
    }

    private function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
