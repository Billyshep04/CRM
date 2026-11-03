<?php

namespace App\Services;

use App\Models\SocialReport;
use App\Services\Concerns\FormatsReportFigures;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class SocialReportPdfService
{
    use FormatsReportFigures;

    /**
     * Colours for the share-of-followers bar, in report order.
     */
    private const SHARE_COLOURS = ['#0d1c23', '#00a3ff', '#30b8f0', '#7fd0f4', '#bfe5f7', '#5b6b73'];

    public function __construct(private readonly SocialReportImageService $images)
    {
    }

    public function render(SocialReport $report): DomPdf
    {
        $report->loadMissing('customer');

        // DomPDF caches metrics for the embedded fonts here and fails if the folder is missing.
        File::ensureDirectoryExists(config('dompdf.options.font_dir', storage_path('fonts')));

        return Pdf::loadView('pdf.social-report', $this->viewData($report))
            ->setPaper('a4', 'portrait');
    }

    /**
     * Everything the template prints, already formatted, so the Blade file only lays it out.
     *
     * @return array<string, mixed>
     */
    public function viewData(SocialReport $report): array
    {
        $data = $report->data ?? [];
        $month = Carbon::parse($report->report_month)->startOfMonth();
        $previousMonth = $month->copy()->subMonthNoOverflow();
        $channels = $report->channels();
        $totals = $data['totals'] ?? [];
        $prepared = $data['prepared_by'] ?? [];

        $pages = [];
        foreach ($channels as $key) {
            $pages[] = $this->platformPage($key, $data['platforms'][$key] ?? [], $month);
        }

        $sum = fn (string $field): array => [
            'current' => $this->sumOrNull(array_map(fn (array $page) => $page['pairs'][$field]['current'], $pages)),
            'previous' => $this->sumOrNull(array_map(fn (array $page) => $page['pairs'][$field]['previous'], $pages)),
        ];
        $followers = $sum('followers');
        $reach = $sum('reach');
        $posts = $sum('posts');
        $engagements = $this->pair($totals, 'engagements');
        $engagementRate = $this->pair($totals, 'engagement_rate');
        $clicks = $this->pair($totals, 'website_clicks');

        $periodLabel = $month->format('d M').' – '.$month->copy()->endOfMonth()->format('d M Y');

        return [
            'report' => $report,
            'customer' => $report->customer,
            'logo' => $this->logoDataUri(),
            'fonts' => $this->fontFaces(),
            'title' => $month->format('F Y').' Social Media Report',
            'monthLabel' => $month->format('F Y'),
            'previousMonthLabel' => $previousMonth->format('F Y'),
            'periodLabel' => $periodLabel,
            'issueDate' => Carbon::parse($report->issue_date)->format('d M Y'),
            'contactName' => trim((string) ($data['contact_name'] ?? '')),
            'contactEmail' => trim((string) ($data['contact_email'] ?? '')),
            'summary' => trim((string) ($data['summary'] ?? '')),
            'phone' => trim((string) ($prepared['phone'] ?? '')) ?: '07979 971328',
            'email' => trim((string) ($prepared['email'] ?? '')) ?: 'info@web-stamp.co.uk',
            'platformNames' => array_map(fn (string $key): string => SocialReport::PLATFORMS[$key]['name'], $channels),
            'overviewSubtitle' => $this->overviewSubtitle($pages, $periodLabel, $previousMonth->format('F Y')),
            'totalPages' => 2 + count($pages),

            'glance' => [
                'followers' => [
                    'label' => 'Total followers',
                    'value' => $this->number($followers['current']),
                    'change' => $this->growthTrend($followers, '%s (%s)', '+'),
                ],
                'reach' => [
                    'label' => 'Total reach',
                    'value' => $this->compact($reach['current']),
                    'change' => $this->percentTrend($reach),
                ],
                'engagementRate' => [
                    'label' => 'Avg. engagement rate',
                    'value' => $this->rate($engagementRate['current']),
                    'change' => $this->pointsTrend($engagementRate, signed: true),
                ],
            ],
            'cards' => [
                $this->overviewCard('Total followers', $followers, 'number', 'percent'),
                $this->overviewCard('Total reach', $reach, 'compact', 'percent'),
                $this->overviewCard('Engagements', $engagements, 'number', 'percent'),
                $this->overviewCard('Avg. engagement rate', $engagementRate, 'rate', 'points'),
                $this->overviewCard('Posts published', $posts, 'number', 'count'),
                $this->overviewCard('Clicks to website', $clicks, 'number', 'percent'),
            ],
            'share' => $this->share($pages),
            'comparison' => array_map(fn (array $page): array => [
                'name' => $page['name'],
                'followers' => $this->number($page['pairs']['followers']['current']) ?? '–',
                'growth' => $this->growthTrend($page['pairs']['followers'], '%s · %s', '+'),
                'reach' => $this->compact($page['pairs']['reach']['current']) ?? '–',
                'engagement' => $this->rate($page['pairs']['engagement_rate']['current']) ?? '–',
                'posts' => $this->number($page['pairs']['posts']['current']) ?? '–',
            ], $pages),

            'pages' => $pages,
        ];
    }

    /**
     * @param  array<string, mixed>  $platform
     * @return array<string, mixed>
     */
    private function platformPage(string $key, array $platform, Carbon $month): array
    {
        $definition = SocialReport::PLATFORMS[$key];
        $metrics = $platform['metrics'] ?? [];
        $followers = $this->pair($metrics, 'followers');

        $cards = [];
        foreach ($definition['cards'] as [$metric, $label, $kind]) {
            $cards[] = $kind === 'duration'
                ? $this->durationCard($label, $platform['avg_duration'] ?? [])
                : $this->platformCard($label, $this->pair($metrics, $metric), $kind);
        }

        $postsCount = $this->pair($platform, 'posts')['current'];

        return [
            'key' => $key,
            'name' => $definition['name'],
            'handle' => trim((string) ($platform['handle'] ?? '')),
            'postsCount' => $this->number($postsCount),
            'postsWord' => $postsCount === 1.0 ? rtrim($definition['posts_word'], 's') : $definition['posts_word'],
            'cards' => $cards,
            'historyLabel' => $definition['history'],
            'chart' => $this->chart($month, $platform['history'] ?? [], $followers),
            'audience' => array_map(fn (string $label, int $index): array => [
                'label' => $label,
                'value' => $this->audienceValue($platform['audience'][$index] ?? null),
            ], $definition['audience'], array_keys($definition['audience'])),
            'postsColumn' => $definition['posts_column'],
            'averageColumn' => $definition['average_column'],
            'formats' => array_map(fn (array $row): array => [
                'label' => trim((string) ($row['label'] ?? '')),
                'posts' => $this->number($this->num($row['posts'] ?? null)) ?? '–',
                'average' => $this->number($this->num($row['average'] ?? null)) ?? '–',
                'engagement' => $this->rate($this->num($row['engagement'] ?? null)) ?? '–',
            ], $platform['formats'] ?? []),
            'topLabel' => $definition['top_label'],
            'topPosts' => array_map(fn (array $post): array => [
                'title' => trim((string) ($post['title'] ?? '')),
                'image' => $this->images->dataUri($post['image'] ?? null),
                'stat' => $this->compact($this->num($post['stat'] ?? null)),
                'statLabel' => $definition['post_metric'],
                'metric' => $this->number($this->num($post['metric'] ?? null)),
                'metricLabel' => trim((string) ($post['metric_label'] ?? '')),
            ], $platform['top_posts'] ?? []),
            'insight' => trim((string) ($platform['insight'] ?? '')),
            // Raw figures for the overview page.
            'pairs' => [
                'followers' => $followers,
                'reach' => $this->pair($metrics, 'reach'),
                'engagement_rate' => $this->pair($metrics, 'engagement_rate'),
                'posts' => $this->pair($platform, 'posts'),
            ],
        ];
    }

    /**
     * @param  array{current: float|null, previous: float|null}  $pair
     * @return array{label: string, value: string|null, change: array<string, string>|null}
     */
    private function platformCard(string $label, array $pair, string $kind): array
    {
        $value = match ($kind) {
            'compact' => $this->compact($pair['current']),
            'rate' => $this->rate($pair['current']),
            'hours' => $pair['current'] === null ? null : $this->number($pair['current']).' hrs',
            default => $this->number($pair['current']),
        };

        $change = match ($kind) {
            'count' => $this->growthTrend($pair, '%s · %s'),
            'rate' => $this->pointsTrend($pair),
            default => $this->percentTrend($pair),
        };

        return ['label' => $label, 'value' => $value, 'change' => $change];
    }

    /**
     * @param  array<string, mixed>  $duration
     */
    private function durationCard(string $label, array $duration): array
    {
        $current = $this->seconds($duration['current'] ?? null);
        $previous = $this->seconds($duration['previous'] ?? null);

        return [
            'label' => $label,
            'value' => $current === null ? null : $this->clock($current),
            'change' => $current === null || $previous === null ? null : $this->trend($current - $previous, $this->clock(abs($current - $previous))),
        ];
    }

    /**
     * @param  array{current: float|null, previous: float|null}  $pair
     */
    private function overviewCard(string $label, array $pair, string $format, string $changeKind): array
    {
        $formatValue = fn (?float $value): ?string => match ($format) {
            'compact' => $this->compact($value),
            'rate' => $this->rate($value),
            default => $this->number($value),
        };

        $change = match ($changeKind) {
            'points' => $this->pointsTrend($pair),
            'count' => $pair['current'] === null || $pair['previous'] === null
                ? null
                : $this->trend($pair['current'] - $pair['previous'], $this->number(abs($pair['current'] - $pair['previous']))),
            default => $this->percentTrend($pair),
        };

        return [
            'label' => $label,
            'value' => $formatValue($pair['current']),
            'change' => $change,
            'was' => $formatValue($pair['previous']),
        ];
    }

    /**
     * Follower growth as "412 · 9.1%". The previous figure must be known to work it out.
     *
     * @param  array{current: float|null, previous: float|null}  $pair
     */
    private function growthTrend(array $pair, string $format, string $sign = ''): ?array
    {
        if ($pair['current'] === null || $pair['previous'] === null) {
            return null;
        }

        $diff = $pair['current'] - $pair['previous'];
        $count = ($diff > 0 ? $sign : ($diff < 0 && $sign !== '' ? '−' : '')).$this->number(abs($diff));
        if (! $pair['previous']) {
            return $this->trend($diff, $count);
        }

        return $this->trend($diff, sprintf($format, $count, number_format(abs($diff) / $pair['previous'] * 100, 1).'%'));
    }

    /**
     * @param  array{current: float|null, previous: float|null}  $pair
     */
    private function percentTrend(array $pair): ?array
    {
        if ($pair['current'] === null || ! $pair['previous']) {
            return null;
        }

        $percent = ($pair['current'] - $pair['previous']) / $pair['previous'] * 100;

        return $this->trend(round($percent, 1), number_format(abs($percent), 1).'%');
    }

    /**
     * @param  array{current: float|null, previous: float|null}  $pair
     */
    private function pointsTrend(array $pair, bool $signed = false): ?array
    {
        if ($pair['current'] === null || $pair['previous'] === null) {
            return null;
        }

        $diff = round($pair['current'] - $pair['previous'], 1);
        $sign = $signed ? ($diff > 0 ? '+' : ($diff < 0 ? '−' : '')) : '';

        return $this->trend($diff, $sign.$this->trimmed(abs($diff)).' pts');
    }

    /**
     * Six monthly bars ending with the report month. The last bar is this month's
     * follower growth, so the chart always agrees with the followers card.
     *
     * @param  array<int, mixed>  $history
     * @param  array{current: float|null, previous: float|null}  $followers
     * @return array<int, array<string, mixed>>
     */
    private function chart(Carbon $month, array $history, array $followers): array
    {
        $values = array_map(fn ($value) => $this->num($value), array_pad(array_slice(array_values($history), 0, 5), 5, null));
        $values[] = $followers['current'] !== null && $followers['previous'] !== null
            ? max(0, $followers['current'] - $followers['previous'])
            : null;

        $max = max(array_map(fn ($value) => $value ?? 0, $values)) ?: 1;
        $maxHeight = 20; // mm

        $bars = [];
        foreach ($values as $index => $value) {
            $bars[] = [
                'label' => $month->copy()->subMonthsNoOverflow(5 - $index)->format('M'),
                'value' => $this->number($value),
                'height' => $value === null ? 0 : max(1, round($value / $max * $maxHeight, 1)),
                'shade' => $index === 5 ? 'current' : ($index === 4 ? 'previous' : 'past'),
            ];
        }

        return $bars;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<int, array{name: string, percent: float, label: string, colour: string}>
     */
    private function share(array $pages): array
    {
        $total = array_sum(array_map(fn (array $page) => $page['pairs']['followers']['current'] ?? 0, $pages));
        if ($total <= 0) {
            return [];
        }

        $segments = [];
        foreach ($pages as $page) {
            $count = $page['pairs']['followers']['current'] ?? 0;
            if ($count <= 0) {
                continue;
            }
            $percent = $count / $total * 100;
            $segments[] = [
                'name' => $page['name'],
                'percent' => round($percent, 2),
                'label' => number_format($percent).'%',
                'colour' => self::SHARE_COLOURS[array_search($page['key'], array_keys(SocialReport::PLATFORMS), true)],
            ];
        }

        return $segments;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     */
    private function overviewSubtitle(array $pages, string $periodLabel, string $previousMonth): string
    {
        $count = count($pages);
        $who = match (true) {
            $count === 1 => $pages[0]['name'],
            $count === 2 => 'Both platforms combined',
            default => 'All '.(['three', 'four', 'five', 'six'][$count - 3] ?? $count).' platforms combined',
        };

        return "{$who}, {$periodLabel} compared with {$previousMonth}";
    }

    private function audienceValue(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '–';
        }

        return is_numeric($value) ? (string) $this->number((float) $value) : $value;
    }

    private function rate(?float $value): ?string
    {
        return $value === null ? null : $this->trimmed($value).'%';
    }

    /**
     * Social reach and views read better short: 82,400 becomes 82.4k.
     */
    private function compact(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value >= 1_000_000) {
            return $this->trimmed($value / 1_000_000).'m';
        }

        if ($value >= 1_000) {
            return $this->trimmed($value / 1_000).'k';
        }

        return $this->number($value);
    }

    private function seconds(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^(\d{1,3}):([0-5]\d)$/', trim($value), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1] * 60 + (int) $matches[2];
    }

    private function clock(int $seconds): string
    {
        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }
}
