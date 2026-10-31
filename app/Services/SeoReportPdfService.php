<?php

namespace App\Services;

use App\Models\SeoReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class SeoReportPdfService
{
    public function render(SeoReport $report): DomPdf
    {
        $report->loadMissing('customer');

        // DomPDF caches metrics for the embedded fonts here and fails if the folder is missing.
        File::ensureDirectoryExists(config('dompdf.options.font_dir', storage_path('fonts')));

        return Pdf::loadView('pdf.seo-report', $this->viewData($report))
            ->setPaper('a4', 'portrait');
    }

    /**
     * Everything the template prints, already formatted, so the Blade file only lays it out.
     *
     * @return array<string, mixed>
     */
    public function viewData(SeoReport $report): array
    {
        $data = $report->data ?? [];
        $month = Carbon::parse($report->report_month)->startOfMonth();
        $previousMonth = $month->copy()->subMonthNoOverflow();
        $metrics = $data['metrics'] ?? [];
        $buckets = $data['ranking_buckets'] ?? [];

        $sessions = $this->pair($metrics, 'sessions');
        $clicks = $this->pair($metrics, 'clicks');
        $impressions = $this->pair($metrics, 'impressions');
        $position = $this->pair($metrics, 'avg_position');
        $enquiries = $this->pair($metrics, 'enquiries');

        // "Keywords in top 10" is the 1–3 and 4–10 ranking buckets added together.
        $topTen = [
            'current' => $this->sumOrNull([$this->pair($buckets, 'top3')['current'], $this->pair($buckets, 'top10')['current']]),
            'previous' => $this->sumOrNull([$this->pair($buckets, 'top3')['previous'], $this->pair($buckets, 'top10')['previous']]),
        ];

        $ctr = [
            'current' => $this->ratio($clicks['current'], $impressions['current']),
            'previous' => $this->ratio($clicks['previous'], $impressions['previous']),
        ];

        $prepared = $data['prepared_by'] ?? [];

        return [
            'report' => $report,
            'customer' => $report->customer,
            'logo' => $this->logoDataUri(),
            'fonts' => $this->fontFaces(),
            'monthLabel' => $month->format('F Y'),
            'title' => $month->format('F Y').' SEO Report',
            'previousMonthLabel' => $previousMonth->format('F Y'),
            'periodLabel' => $month->format('d M').' – '.$month->copy()->endOfMonth()->format('d M Y'),
            'issueDate' => Carbon::parse($report->issue_date)->format('d M Y'),
            'contactName' => trim((string) ($data['contact_name'] ?? '')),
            'contactEmail' => trim((string) ($data['contact_email'] ?? '')),
            'campaign' => $this->campaignLabel($data),
            'summary' => trim((string) ($data['summary'] ?? '')),
            'preparedBy' => [
                'name' => trim((string) ($prepared['name'] ?? '')) ?: 'Billy Sheppard',
                'phone' => trim((string) ($prepared['phone'] ?? '')) ?: '07979 971328',
                'email' => trim((string) ($prepared['email'] ?? '')) ?: 'info@web-stamp.co.uk',
            ],

            'cards' => [
                'sessions' => $this->percentCard('Organic sessions', $sessions),
                'clicks' => $this->percentCard('Search clicks', $clicks),
                'impressions' => $this->percentCard('Impressions', $impressions, compact: true),
                'position' => $this->positionCard('Average position', $position),
                'topTen' => $this->countCard('Keywords in top 10', $topTen),
                'enquiries' => $this->percentCard('Enquiries from search', $enquiries),
            ],
            'keyWins' => $data['key_wins'] ?? [],

            'chart' => $this->chart($month, $data['traffic_history'] ?? [], $sessions),
            'sessionsTotal' => $this->number($sessions['current']),
            'growthSinceStart' => $this->signedPercent($data['growth_since_start'] ?? null),
            'searchConsole' => $this->searchConsoleRows($clicks, $impressions, $ctr, $position),
            'landingPages' => $this->landingPages($data['landing_pages'] ?? []),

            'buckets' => $this->buckets($buckets),
            'trackedKeywords' => $this->sumOrNull(array_map(fn (string $key) => $this->pair($buckets, $key)['current'], ['top3', 'top10', 'top20', 'top50'])),
            'keywords' => $this->keywords($data['keywords'] ?? []),
            'rankingsNote' => trim((string) ($data['rankings_note'] ?? '')),

            'health' => $this->health($data['health'] ?? []),
            'vitals' => $this->vitals($data['vitals'] ?? []),
            'fixes' => array_map(fn (array $fix): array => [
                'count' => $this->number($this->num($fix['count'] ?? null)) ?? '0',
                'label' => trim((string) ($fix['label'] ?? '')),
            ], $data['fixes'] ?? []),
            'authority' => $this->authority($data['authority'] ?? []),
            'newLinks' => array_map(fn (array $link): array => [
                'domain' => trim((string) ($link['domain'] ?? '')),
                'authority' => $this->number($this->num($link['authority'] ?? null)) ?? '–',
                'type' => trim((string) ($link['type'] ?? '')),
            ], $data['new_links'] ?? []),

            'workItems' => $data['work_items'] ?? [],
            'priorities' => $data['priorities'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{current: float|null, previous: float|null}
     */
    private function pair(array $source, string $key): array
    {
        return [
            'current' => $this->num($source[$key]['current'] ?? null),
            'previous' => $this->num($source[$key]['previous'] ?? null),
        ];
    }

    private function num(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<int, float|null>  $values
     */
    private function sumOrNull(array $values): ?float
    {
        $present = array_filter($values, fn ($value) => $value !== null);

        return $present === [] ? null : array_sum($present);
    }

    private function ratio(?float $numerator, ?float $denominator): ?float
    {
        if ($numerator === null || ! $denominator) {
            return null;
        }

        return round($numerator / $denominator * 100, 1);
    }

    private function number(?float $value, int $decimals = 0): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($decimals === 0 && floor($value) !== $value) {
            $decimals = 1;
        }

        return number_format($value, $decimals);
    }

    private function compact(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value >= 1_000_000) {
            return rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.').'m';
        }

        if ($value >= 100_000) {
            return rtrim(rtrim(number_format($value / 1_000, 1), '0'), '.').'k';
        }

        return $this->number($value);
    }

    private function signedPercent(mixed $value): ?string
    {
        $value = $this->num($value);
        if ($value === null) {
            return null;
        }

        return ($value >= 0 ? '+' : '−').$this->trimmed(abs($value)).'%';
    }

    private function trimmed(float $value, int $decimals = 1): string
    {
        return rtrim(rtrim(number_format($value, $decimals), '0'), '.');
    }

    /**
     * @param  array{current: float|null, previous: float|null}  $pair
     * @return array{label: string, value: string|null, change: array<string, string>|null, was: string|null}
     */
    private function percentCard(string $label, array $pair, bool $compact = false): array
    {
        $format = fn (?float $value) => $compact ? $this->compact($value) : $this->number($value);
        $change = null;

        if ($pair['current'] !== null && $pair['previous']) {
            $percent = ($pair['current'] - $pair['previous']) / $pair['previous'] * 100;
            $change = $this->trend($percent, number_format(abs($percent), 1).'%');
        }

        return [
            'label' => $label,
            'value' => $format($pair['current']),
            'change' => $change,
            'was' => $format($pair['previous']),
        ];
    }

    /**
     * Lower is better for search position, so a falling number is shown as an improvement.
     *
     * @param  array{current: float|null, previous: float|null}  $pair
     */
    private function positionCard(string $label, array $pair): array
    {
        $change = null;
        if ($pair['current'] !== null && $pair['previous'] !== null) {
            $diff = $pair['previous'] - $pair['current'];
            $change = $this->trend($diff, $this->trimmed(abs($diff)).' '.(abs($diff) == 1.0 ? 'place' : 'places'));
        }

        return [
            'label' => $label,
            'value' => $pair['current'] === null ? null : $this->trimmed($pair['current']),
            'change' => $change,
            'was' => $pair['previous'] === null ? null : $this->trimmed($pair['previous']),
        ];
    }

    /**
     * @param  array{current: float|null, previous: float|null}  $pair
     */
    private function countCard(string $label, array $pair): array
    {
        $change = null;
        if ($pair['current'] !== null && $pair['previous'] !== null) {
            $diff = $pair['current'] - $pair['previous'];
            $change = $this->trend($diff, ($diff > 0 ? '+' : ($diff < 0 ? '−' : '')).$this->number(abs($diff)));
        }

        return [
            'label' => $label,
            'value' => $this->number($pair['current']),
            'change' => $change,
            'was' => $this->number($pair['previous']),
        ];
    }

    /**
     * @return array{tone: string, arrow: string, text: string}
     */
    private function trend(float $delta, string $text): array
    {
        if (abs($delta) < 0.0001) {
            return ['tone' => 'flat', 'arrow' => '', 'text' => 'No change'];
        }

        return $delta > 0
            ? ['tone' => 'up', 'arrow' => '▲', 'text' => $text]
            : ['tone' => 'down', 'arrow' => '▼', 'text' => $text];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function campaignLabel(array $data): ?string
    {
        $monthNumber = (int) ($data['campaign_month'] ?? 0);
        $length = (int) ($data['campaign_length'] ?? 0);

        if ($monthNumber < 1) {
            return null;
        }

        return $length > 0 ? "{$monthNumber} of {$length}" : (string) $monthNumber;
    }

    /**
     * Twelve monthly bars ending with the report month. The last two come from the
     * sessions metric so the chart always agrees with the summary page.
     *
     * @param  array<int, mixed>  $history
     * @param  array{current: float|null, previous: float|null}  $sessions
     * @return array<int, array<string, mixed>>
     */
    private function chart(Carbon $month, array $history, array $sessions): array
    {
        $values = array_map(fn ($value) => $this->num($value), array_pad(array_slice(array_values($history), 0, 10), 10, null));
        $values[] = $sessions['previous'];
        $values[] = $sessions['current'];

        $max = max(array_map(fn ($value) => $value ?? 0, $values)) ?: 1;
        $maxHeight = 44; // mm

        $bars = [];
        foreach ($values as $index => $value) {
            $bars[] = [
                'label' => $month->copy()->subMonthsNoOverflow(11 - $index)->format('M'),
                'height' => $value === null ? 0 : max(1, round($value / $max * $maxHeight, 1)),
                'shade' => $index === 11 ? 'current' : ($index === 10 ? 'previous' : 'past'),
            ];
        }

        return $bars;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchConsoleRows(array $clicks, array $impressions, array $ctr, array $position): array
    {
        $percentChange = function (array $pair): ?array {
            if ($pair['current'] === null || ! $pair['previous']) {
                return null;
            }
            $percent = ($pair['current'] - $pair['previous']) / $pair['previous'] * 100;

            return $this->changePill($percent, ($percent >= 0 ? '+' : '−').number_format(abs($percent), 1).'%');
        };

        $rows = [
            ['label' => 'Clicks', 'current' => $this->number($clicks['current']), 'previous' => $this->number($clicks['previous']), 'change' => $percentChange($clicks)],
            ['label' => 'Impressions', 'current' => $this->number($impressions['current']), 'previous' => $this->number($impressions['previous']), 'change' => $percentChange($impressions)],
        ];

        $ctrChange = null;
        if ($ctr['current'] !== null && $ctr['previous'] !== null) {
            $diff = round($ctr['current'] - $ctr['previous'], 1);
            $ctrChange = $this->changePill($diff, ($diff >= 0 ? '+' : '−').$this->trimmed(abs($diff)).' pts');
        }
        $rows[] = [
            'label' => 'Click-through rate',
            'current' => $ctr['current'] === null ? null : $this->trimmed($ctr['current']).'%',
            'previous' => $ctr['previous'] === null ? null : $this->trimmed($ctr['previous']).'%',
            'change' => $ctrChange,
        ];

        $positionChange = null;
        if ($position['current'] !== null && $position['previous'] !== null) {
            $diff = round($position['previous'] - $position['current'], 1);
            $positionChange = $this->changePill($diff, ($diff >= 0 ? '+' : '−').$this->trimmed(abs($diff)));
        }
        $rows[] = [
            'label' => 'Average position',
            'current' => $position['current'] === null ? null : $this->trimmed($position['current']),
            'previous' => $position['previous'] === null ? null : $this->trimmed($position['previous']),
            'change' => $positionChange,
        ];

        return array_values(array_filter($rows, fn (array $row) => $row['current'] !== null));
    }

    /**
     * @return array{tone: string, text: string}
     */
    private function changePill(float $delta, string $text): array
    {
        if (abs($delta) < 0.0001) {
            return ['tone' => 'flat', 'text' => 'No change'];
        }

        return ['tone' => $delta > 0 ? 'up' : 'down', 'text' => $text];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<int, array<string, mixed>>
     */
    private function landingPages(array $pages): array
    {
        return array_map(function (array $page): array {
            $sessions = $this->num($page['sessions'] ?? null);
            $previous = $this->num($page['previous'] ?? null);

            if ($previous === null || $previous == 0.0) {
                $change = ['tone' => 'up', 'text' => 'New'];
            } elseif ($sessions === null) {
                $change = null;
            } else {
                $percent = ($sessions - $previous) / $previous * 100;
                $change = $this->changePill(round($percent), ($percent >= 0 ? '+' : '−').number_format(abs($percent)).'%');
            }

            return [
                'path' => trim((string) ($page['path'] ?? '')),
                'sessions' => $this->number($sessions) ?? '–',
                'change' => $change,
            ];
        }, $pages);
    }

    /**
     * @param  array<string, mixed>  $buckets
     * @return array<int, array<string, mixed>>
     */
    private function buckets(array $buckets): array
    {
        $labels = ['top3' => 'Positions 1–3', 'top10' => 'Positions 4–10', 'top20' => 'Positions 11–20', 'top50' => 'Positions 21–50'];
        $styles = ['top3' => 'accent', 'top10' => 'light', 'top20' => 'muted', 'top50' => 'muted'];
        $cards = [];

        foreach ($labels as $key => $label) {
            $pair = $this->pair($buckets, $key);
            $change = null;
            if ($pair['current'] !== null && $pair['previous'] !== null) {
                $diff = $pair['current'] - $pair['previous'];
                $change = ($diff > 0 ? '+' : ($diff < 0 ? '−' : '±')).$this->number(abs($diff)).' this month';
            }

            $cards[] = [
                'label' => $label,
                'value' => $this->number($pair['current']) ?? '–',
                'change' => $change,
                'style' => $styles[$key],
            ];
        }

        return $cards;
    }

    /**
     * @param  array<int, array<string, mixed>>  $keywords
     * @return array<int, array<string, mixed>>
     */
    private function keywords(array $keywords): array
    {
        return array_map(function (array $row): array {
            $current = $this->num($row['current'] ?? null);
            $previous = $this->num($row['previous'] ?? null);

            if ($current !== null && $previous === null) {
                $change = ['tone' => 'up', 'text' => 'New'];
            } elseif ($current === null || $previous === null) {
                $change = ['tone' => 'flat', 'text' => '–'];
            } else {
                $diff = $previous - $current;
                $change = $diff == 0.0
                    ? ['tone' => 'flat', 'text' => '–']
                    : ['tone' => $diff > 0 ? 'up' : 'down', 'text' => ($diff > 0 ? '▲ ' : '▼ ').$this->number(abs($diff))];
            }

            return [
                'keyword' => trim((string) ($row['keyword'] ?? '')),
                'searches' => $this->number($this->num($row['searches'] ?? null)) ?? '–',
                'previous' => $this->number($previous) ?? '–',
                'current' => $this->number($current) ?? '–',
                'band' => $current === null ? 'muted' : ($current <= 3 ? 'accent' : ($current <= 10 ? 'light' : 'muted')),
                'change' => $change,
            ];
        }, $keywords);
    }

    /**
     * @param  array<string, mixed>  $health
     */
    private function health(array $health): ?array
    {
        $current = $this->num($health['current'] ?? null);
        if ($current === null) {
            return null;
        }

        return [
            'score' => (int) $current,
            'previous' => $this->num($health['previous'] ?? null) === null ? null : (int) $health['previous'],
            'ring' => $this->ringDataUri((int) $current),
        ];
    }

    /**
     * Ratings use Google's published Core Web Vitals thresholds.
     *
     * @param  array<string, mixed>  $vitals
     */
    private function vitals(array $vitals): ?array
    {
        $definitions = [
            'lcp' => ['Largest Contentful Paint', 'How fast the main content loads', 2.5, 4.0, fn (float $v) => $this->trimmed($v).'s'],
            'inp' => ['Interaction to Next Paint', 'How quickly the page responds to taps', 200, 500, fn (float $v) => number_format($v).'ms'],
            'cls' => ['Cumulative Layout Shift', 'How stable the layout is while loading', 0.1, 0.25, fn (float $v) => rtrim(rtrim(number_format($v, 3), '0'), '.') ?: '0'],
        ];

        $rows = [];
        foreach ($definitions as $key => [$label, $help, $good, $poor, $format]) {
            $value = $this->num($vitals[$key] ?? null);
            if ($value === null) {
                continue;
            }

            $rating = $value <= $good ? 'good' : ($value <= $poor ? 'needs' : 'poor');
            $rows[] = [
                'label' => $label,
                'help' => $help,
                'value' => $format($value),
                'rating' => $rating,
                'ratingLabel' => ['good' => 'Good', 'needs' => 'Needs work', 'poor' => 'Poor'][$rating],
            ];
        }

        if ($rows === []) {
            return null;
        }

        $allMeasured = count($rows) === count($definitions);
        $allGood = $allMeasured && collect($rows)->every(fn (array $row) => $row['rating'] === 'good');

        return [
            'rows' => $rows,
            'status' => $allMeasured ? ($allGood ? 'Passed' : 'Not passed') : null,
            'passed' => $allGood,
        ];
    }

    /**
     * @param  array<string, mixed>  $authority
     * @return array<int, array<string, mixed>>
     */
    private function authority(array $authority): array
    {
        $labels = ['domain_authority' => 'Domain authority', 'referring_domains' => 'Referring domains', 'backlinks' => 'Total backlinks'];
        $cards = [];

        foreach ($labels as $key => $label) {
            $pair = $this->pair($authority, $key);
            if ($pair['current'] === null) {
                continue;
            }

            $change = null;
            if ($pair['previous'] !== null) {
                $diff = $pair['current'] - $pair['previous'];
                $change = $diff == 0.0
                    ? ['tone' => 'flat', 'text' => 'No change since last month']
                    : ['tone' => $diff > 0 ? 'up' : 'down', 'text' => ($diff > 0 ? '+' : '−').$this->number(abs($diff)).' since last month'];
            }

            $cards[] = ['label' => $label, 'value' => $this->number($pair['current']), 'change' => $change];
        }

        return $cards;
    }

    /**
     * DomPDF draws SVG reliably but has no conic gradients, so the score ring is an SVG arc.
     */
    private function ringDataUri(int $score): string
    {
        $score = max(0, min(100, $score));
        $size = 120;
        $radius = 52;
        $stroke = 13;
        $centre = $size / 2;

        $arc = '';
        if ($score >= 100) {
            $arc = sprintf('<circle cx="%1$s" cy="%1$s" r="%2$s" fill="none" stroke="#30b8f0" stroke-width="%3$s"/>', $centre, $radius, $stroke);
        } elseif ($score > 0) {
            $angle = deg2rad($score / 100 * 360 - 90);
            $endX = $centre + $radius * cos($angle);
            $endY = $centre + $radius * sin($angle);
            $largeArc = $score > 50 ? 1 : 0;
            $arc = sprintf(
                '<path d="M %1$s %2$s A %3$s %3$s 0 %4$d 1 %5$.3f %6$.3f" fill="none" stroke="#30b8f0" stroke-width="%7$s" stroke-linecap="round"/>',
                $centre, $centre - $radius, $radius, $largeArc, $endX, $endY, $stroke
            );
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d"><circle cx="%2$s" cy="%2$s" r="%3$s" fill="none" stroke="#24343b" stroke-width="%4$s"/>%5$s</svg>',
            $size, $centre, $radius, $stroke, $arc
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function logoDataUri(): ?string
    {
        $path = resource_path('images/new-logo-white-text.svg');
        $svg = is_file($path) ? file_get_contents($path) : false;

        return $svg ? 'data:image/svg+xml;base64,'.base64_encode($svg) : null;
    }

    /**
     * @return array<int, array{family: string, weight: int, path: string}>
     */
    private function fontFaces(): array
    {
        $faces = [];
        foreach ([['Inter', 400], ['Inter', 500], ['Inter', 600], ['Inter', 700], ['Inter', 800], ['Montserrat', 800]] as [$family, $weight]) {
            $path = resource_path('fonts/'.strtolower($family)."-{$weight}.ttf");
            if (is_file($path)) {
                $faces[] = ['family' => $family, 'weight' => $weight, 'path' => $path];
            }
        }

        return $faces;
    }
}
