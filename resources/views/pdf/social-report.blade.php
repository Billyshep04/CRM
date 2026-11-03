<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} · {{ $customer?->name }}</title>
    <style>
        @include('pdf.partials.report-base-styles')

        /* Cover */
        .cover-rule { height: 3.7mm; background: #30b8f0; }
        .cover-hero { position: relative; height: 132mm; padding: 14mm 14.8mm 0; background: #0d1c23; color: #ffffff; }
        .cover-logo { width: 67.5mm; height: 32.5mm; }
        .cover-eyebrow { margin-top: 16mm; color: #30b8f0; font-size: 9pt; font-weight: 700; letter-spacing: .8mm; text-transform: uppercase; }
        .cover-title { margin-top: 3.2mm; font-size: 37pt; font-weight: 800; letter-spacing: -.5mm; }
        .cover-title div { height: 14mm; line-height: 1.05; }
        .cover-platforms { margin-top: 9mm; }
        .platform-pill { display: inline-block; margin: 0 1.8mm 1.8mm 0; padding: 1.5mm 3.4mm; border: .45mm solid #30b8f0; border-radius: 4mm; color: #ffffff; font-size: 9.2pt; font-weight: 600; line-height: 1.2; }
        .cover-period { position: absolute; right: 0; top: 121.3mm; height: 8.1mm; padding: 3.8mm 9mm 0 7.5mm; border-radius: 6mm 0 0 6mm; background: #30b8f0; color: #0d1c23; font-size: 11pt; font-weight: 700; line-height: 1; letter-spacing: .1mm; text-transform: uppercase; white-space: nowrap; }
        .cover-period span { color: #ffffff; }
        .cover-body { padding: 10.5mm 12.75mm 0; }
        .cover-card { height: 37mm; padding: 7.5mm 6.8mm 0; }
        .cover-name { margin-top: 3mm; font-size: 15.5pt; font-weight: 700; line-height: 1.15; }
        .cover-contact { margin-top: 2.2mm; color: #34505c; font-size: 9pt; line-height: 1.45; }
        .detail-label { color: #4d626c; font-size: 6.3pt; font-weight: 700; letter-spacing: .35mm; text-transform: uppercase; }
        .detail-value { margin-top: 1.5mm; font-size: 11pt; font-weight: 700; line-height: 1.15; }
        .compared { margin-top: 5mm; color: #34505c; font-size: 8.8pt; }
        .compared b { color: #0d1c23; font-weight: 700; }
        .glance-card { padding: 5.4mm 6.2mm 4.2mm; line-height: 1.1; }
        .glance-label { font-size: 8.8pt; font-weight: 600; color: #26414c; }
        .card-accent .glance-label { color: #0d1c23; }
        .glance-value { margin: 2.4mm 0 2.4mm; font-size: 26pt; line-height: 1; }
        .card-change { height: 6mm; line-height: 1; }

        /* Overall performance */
        .brief { margin-top: 5.4mm; padding: 4.6mm 6.4mm 4.6mm; }
        .brief-text { margin-top: 2mm; color: #1b2f38; font-size: 9.8pt; line-height: 1.38; }
        .metric-card { padding: 3.8mm 5.6mm 2.8mm; line-height: 1.1; }
        .metric-label { font-size: 8.6pt; font-weight: 600; color: #26414c; }
        .metric-value { margin: 1mm 0 1.4mm; font-size: 21pt; line-height: 1; }
        .card-accent .metric-label { color: #0d1c23; }
        .card-accent .was { color: #0d1c23; }
        .share-bar { width: 100%; table-layout: fixed; }
        .share-bar td { padding-right: .5mm; }
        .share-bar td.last { padding-right: 0; }
        .share-segment { height: 6.7mm; }
        .share-segment.first { border-radius: 1.6mm 0 0 1.6mm; }
        .share-segment.last { border-radius: 0 1.6mm 1.6mm 0; }
        .share-segment.only { border-radius: 1.6mm; }
        .legend { margin-top: 2.6mm; }
        .legend-item { display: inline-block; margin-right: 5mm; color: #26414c; font-size: 8.6pt; font-weight: 600; white-space: nowrap; }
        .legend-dot { display: inline-block; width: 2.6mm; height: 2.6mm; margin-right: 1.4mm; border-radius: .6mm; vertical-align: -.2mm; }
        .compare-table td { height: 8.8mm; }
        .compare-table .pill { padding: .7mm 2.4mm; }
        .overview .section-label { margin: 5.4mm 0 2.6mm; }
        .platform-page .section-label { margin: 5.4mm 0 2.8mm; }
        .platform-page .table-head td { height: 9mm; }
        .platform-page .data-table td { height: 9mm; }

        /* Platform pages */
        .handle-row { width: 100%; margin-top: -1mm; }
        .handle-row td { vertical-align: bottom; }
        .handle { color: #0a6ea6; font-size: 10.4pt; font-weight: 600; }
        .posts-count { text-align: right; color: #4d626c; font-size: 9.6pt; }
        .posts-count b { color: #0d1c23; font-size: 11pt; font-weight: 700; }
        .stat-card { padding: 3.6mm 4.4mm 2.6mm; line-height: 1.1; }
        .card-accent.stat-card { border: .45mm solid #30b8f0; }
        .stat-label { font-size: 8.4pt; font-weight: 600; color: #26414c; }
        .card-accent .stat-label { color: #0d1c23; }
        .stat-value { margin: .8mm 0 1mm; font-size: 20pt; line-height: 1; }
        .stat-change { height: 5.6mm; line-height: 1; }
        .stat-card .pill { font-size: 7.8pt; padding: .8mm 2.2mm; }
        .chart-box { height: 41mm; padding: 5.6mm 5.2mm 0; }
        .audience { height: 42.4mm; padding: 5.1mm 5.2mm 0; }
        .sm-bars { width: 100%; margin-top: 2.6mm; table-layout: fixed; }
        .sm-bars td { height: 25.5mm; vertical-align: bottom; padding: 0 1.4mm; text-align: center; }
        .bar-value { margin-bottom: 1.2mm; color: #26414c; font-size: 7.4pt; font-weight: 700; }
        .bar-value.current { color: #0d1c23; }
        .sm-bar { border-radius: 1.2mm 1.2mm 0 0; }
        .sm-labels { width: 100%; table-layout: fixed; }
        .sm-labels td { padding-top: 1.6mm; text-align: center; color: #4d626c; font-size: 7.6pt; font-weight: 600; }
        .sm-labels td.current { color: #0d1c23; font-weight: 700; }
        .bar-past { background: #bfe5f7; }
        .bar-previous { background: #7fd0f4; }
        .bar-current { background: #00a3ff; }
        .audience-row { width: 100%; border-bottom: .35mm solid #dfe8ec; }
        .audience-row.last { border-bottom: none; }
        .audience-row td { height: 8.7mm; vertical-align: middle; }
        .audience-label { color: #34505c; font-size: 8.8pt; line-height: 1.2; }
        .audience-value { text-align: right; font-size: 10.2pt; font-weight: 700; line-height: 1.15; }
        .post-card { height: 42mm; }
        .post-thumb { height: 20.3mm; background: #e8f5fc; border-radius: 2.2mm 2.2mm 0 0; }
        .post-thumb img { display: block; width: 100%; height: 20.3mm; border-radius: 2.2mm 2.2mm 0 0; }
        .post-body { padding: 3.4mm 3.6mm 0; }
        .post-title { height: 10.6mm; font-size: 9.4pt; font-weight: 700; line-height: 1.3; overflow: hidden; }
        .post-stats { margin-top: 1.6mm; color: #4d626c; font-size: 8.4pt; }
        .post-stats b { color: #0d1c23; font-weight: 700; }
                .insight { margin-top: 4mm; padding: 5mm 6mm 5mm; }
        .insight table { width: 100%; }
        .insight-label { width: 23mm; padding-top: .8mm; }
        .insight-text { color: #1b2f38; font-size: 9.6pt; line-height: 1.55; }
        .empty { margin-top: 4mm; color: #6b7f88; font-size: 9pt; }
    </style>
</head>
<body>
@php
    $sectionNumber = fn (int $number): string => str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    $pill = function (?array $change, bool $dark = false): string {
        if (! $change) {
            return '';
        }
        $arrow = $change['arrow'] !== '' ? '<span class="arrow">'.$change['arrow'].'</span>&nbsp; ' : '';

        return '<span class="pill '.($dark ? 'pill-dark' : 'pill-'.$change['tone']).'">'.$arrow.e($change['text']).'</span>';
    };
@endphp

{{-- Page 1: cover --}}
<div class="page{{ $totalPages === 1 ? ' last' : '' }}">
    <div class="cover-rule"></div>
    <div class="cover-hero">
        @if ($logo)
            <img class="cover-logo" src="{{ $logo }}" alt="Web Stamp">
        @endif
        <div class="cover-eyebrow">Social Media Growth Report</div>
        <div class="cover-title"><div>{{ $monthLabel }}</div><div>Social Media Report</div></div>
        <div class="cover-platforms">
            @foreach ($platformNames as $name)<span class="platform-pill">{{ $name }}</span>@endforeach
        </div>
        <div class="cover-period">Reporting period: <span>{{ $periodLabel }}</span></div>
    </div>

    <div class="cover-body">
        <table class="grid">
            <tr>
                <td style="width: 48.2%;">
                    <div class="card card-grey cover-card">
                        <div class="eyebrow">Prepared for</div>
                        <div class="cover-name">{{ $customer?->name }}</div>
                        <div class="cover-contact">
                            @if ($contactName !== ''){{ $contactName }}<br>@endif
                            @if ($contactEmail !== ''){{ $contactEmail }}@endif
                        </div>
                    </div>
                </td>
                <td style="width: 3.6%;"></td>
                <td style="width: 48.2%;">
                    <div class="card card-light cover-card">
                        <div class="eyebrow">Report details</div>
                        <table style="width: 100%; margin-top: 4.5mm; table-layout: fixed;">
                            <tr>
                                <td style="width: 36%;"><div class="detail-label">Issued</div><div class="detail-value">{{ $issueDate }}</div></td>
                                <td style="width: 36%;"><div class="detail-label">Report no.</div><div class="detail-value">{{ $report->report_number }}</div></td>
                                <td style="width: 28%;"><div class="detail-label">Platforms</div><div class="detail-value">{{ count($platformNames) }}</div></td>
                            </tr>
                        </table>
                        <div class="compared">Compared with <b>{{ $previousMonthLabel }}</b></div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="eyebrow" style="margin: 9mm 0 3.2mm;">At a glance</div>
        <table class="grid">
            <tr>
                @foreach ([['card' => $glance['followers'], 'accent' => false], ['card' => $glance['reach'], 'accent' => false], ['card' => $glance['engagementRate'], 'accent' => true]] as $index => $item)
                    @if ($index > 0)<td class="gap3"></td>@endif
                    <td class="col3">
                        <div class="card {{ $item['accent'] ? 'card-accent' : 'card-outline' }} glance-card">
                            <div class="glance-label">{{ $item['card']['label'] }}</div>
                            <div class="big-number glance-value">{{ $item['card']['value'] ?? '–' }}</div>
                            <div class="card-change">{!! $pill($item['card']['change'], $item['accent']) !!}</div>
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

    <div class="footer">
        <table><tr>
            <td class="left">web-stamp.co.uk</td>
            <td class="right" style="width: 65%; letter-spacing: 0;">{{ $phone }} · {{ $email }}</td>
        </tr></table>
    </div>
</div>

{{-- Page 2: all platforms --}}
@include('pdf.partials.seo-report-header', ['number' => 2, 'section' => 'All platforms'])
    <div class="content overview">
        <div class="page-title">Overall social performance</div>
        <div class="page-subtitle">{{ $overviewSubtitle }}</div>

        @if ($summary !== '')
            <div class="card card-grey brief">
                <div class="eyebrow">This month in brief</div>
                <div class="brief-text">{!! nl2br(e($summary)) !!}</div>
            </div>
        @endif

        @foreach (array_chunk($cards, 3) as $rowIndex => $row)
            <table class="grid" style="margin-top: {{ $rowIndex === 0 ? ($summary !== '' ? '4.6mm' : '7.3mm') : '3.2mm' }};">
                <tr>
                    @foreach ($row as $index => $card)
                        @php $accent = $rowIndex === 1 && $index === 2; @endphp
                        @if ($index > 0)<td class="gap3"></td>@endif
                        <td class="col3">
                            <div class="card {{ $accent ? 'card-accent' : 'card-outline' }} metric-card">
                                <div class="metric-label">{{ $card['label'] }}</div>
                                <div class="big-number metric-value">{{ $card['value'] ?? '–' }}</div>
                                <div class="card-change">{!! $pill($card['change'], $accent) !!}@if ($card['was'] !== null)<span class="was">was {{ $card['was'] }}</span>@endif</div>
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endforeach

        @if (count($share) > 1)
            <div class="eyebrow section-label">Share of total followers</div>
            <table class="share-bar">
                <tr>
                    @foreach ($share as $segment)
                        @php $position = count($share) === 1 ? 'only' : ($loop->first ? 'first' : ($loop->last ? 'last' : '')); @endphp
                        <td class="{{ $loop->last ? 'last' : '' }}" style="width: {{ $segment['percent'] }}%;">
                            <div class="share-segment {{ $position }}" style="background: {{ $segment['colour'] }};"></div>
                        </td>
                    @endforeach
                </tr>
            </table>
            <div class="legend">
                @foreach ($share as $segment)
                    <span class="legend-item"><span class="legend-dot" style="background: {{ $segment['colour'] }};"></span>{{ $segment['name'] }} {{ $segment['label'] }}</span>
                @endforeach
            </div>
        @endif

        @if ($comparison !== [])
            <div class="eyebrow section-label">Platform comparison</div>
            <div class="table-head">
                <table><tr>
                    <td style="width: 21%;">Platform</td>
                    <td class="r" style="width: 15%;">Followers</td>
                    <td class="r" style="width: 21%;">Growth</td>
                    <td class="r" style="width: 13%;">Reach</td>
                    <td class="r" style="width: 18%;">Engagement</td>
                    <td class="r" style="width: 12%;">Posts</td>
                </tr></table>
            </div>
            <table class="data-table compare-table">
                @foreach ($comparison as $row)
                    <tr>
                        <td class="name" style="width: 21%;">{{ $row['name'] }}</td>
                        <td class="r" style="width: 15%;">{{ $row['followers'] }}</td>
                        <td class="r" style="width: 21%;">@if ($row['growth'])<span class="pill pill-{{ $row['growth']['tone'] }}">{{ $row['growth']['text'] }}</span>@else – @endif</td>
                        <td class="r" style="width: 13%;">{{ $row['reach'] }}</td>
                        <td class="r" style="width: 18%;">{{ $row['engagement'] }}</td>
                        <td class="r" style="width: 12%;">{{ $row['posts'] }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
@include('pdf.partials.seo-report-footer', ['number' => 2])

{{-- One page per selected channel --}}
@foreach ($pages as $index => $page)
    @php $number = $index + 3; @endphp
    @include('pdf.partials.seo-report-header', ['number' => $number, 'section' => $page['name']])
        <div class="content platform-page">
            <div class="page-title">{{ $page['name'] }}</div>
            <table class="handle-row">
                <tr>
                    <td class="handle">{{ $page['handle'] }}</td>
                    <td class="posts-count">@if ($page['postsCount'] !== null)<b>{{ $page['postsCount'] }}</b> {{ $page['postsWord'] }} this month @endif</td>
                </tr>
            </table>

            <table class="grid" style="margin-top: 4.6mm;">
                <tr>
                    @foreach ($page['cards'] as $cardIndex => $card)
                        @php $accent = $loop->last; @endphp
                        @if ($cardIndex > 0)<td class="gap"></td>@endif
                        <td class="col4">
                            <div class="card {{ $accent ? 'card-accent' : 'card-outline' }} stat-card">
                                <div class="stat-label">{{ $card['label'] }}</div>
                                <div class="big-number stat-value">{{ $card['value'] ?? '–' }}</div>
                                <div class="stat-change">{!! $pill($card['change'], $accent) !!}</div>
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>

            <table class="grid" style="margin-top: 4mm;">
                <tr>
                    <td style="width: 64%;">
                        <div class="card card-outline chart-box">
                            <div class="eyebrow">{{ $page['historyLabel'] }} · last 6 months</div>
                            <table class="sm-bars">
                                <tr>
                                    @foreach ($page['chart'] as $bar)
                                        <td>
                                            @if ($bar['height'] > 0)
                                                <div class="bar-value {{ $bar['shade'] === 'current' ? 'current' : '' }}">{{ $bar['value'] }}</div>
                                                <div class="sm-bar bar-{{ $bar['shade'] }}" style="height: {{ $bar['height'] }}mm;"></div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            </table>
                            <table class="sm-labels">
                                <tr>
                                    @foreach ($page['chart'] as $bar)
                                        <td class="{{ $bar['shade'] === 'current' ? 'current' : '' }}">{{ $bar['label'] }}</td>
                                    @endforeach
                                </tr>
                            </table>
                        </div>
                    </td>
                    <td style="width: 2.3%;"></td>
                    <td style="width: 33.7%;">
                        <div class="card card-grey audience">
                            <div class="eyebrow">Audience snapshot</div>
                            <div style="margin-top: 1.4mm;">
                                @foreach ($page['audience'] as $row)
                                    <table class="audience-row {{ $loop->last ? 'last' : '' }}">
                                        <tr>
                                            <td class="audience-label" style="width: 52%;">{{ $row['label'] }}</td>
                                            <td class="audience-value" style="width: 48%;">{{ $row['value'] }}</td>
                                        </tr>
                                    </table>
                                @endforeach
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            @if ($page['formats'] !== [])
                <div class="eyebrow section-label">Performance by content type</div>
                <div class="table-head">
                    <table><tr>
                        <td style="width: 36%;">Format</td>
                        <td class="r" style="width: 18%;">{{ $page['postsColumn'] }}</td>
                        <td class="r" style="width: 24%;">{{ $page['averageColumn'] }}</td>
                        <td class="r" style="width: 22%;">Avg. engagement</td>
                    </tr></table>
                </div>
                <table class="data-table">
                    @foreach ($page['formats'] as $row)
                        <tr>
                            <td class="name" style="width: 36%;">{{ $row['label'] }}</td>
                            <td class="r" style="width: 18%;">{{ $row['posts'] }}</td>
                            <td class="r" style="width: 24%;">{{ $row['average'] }}</td>
                            <td class="r" style="width: 22%;">{{ $row['engagement'] }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            @if ($page['topPosts'] !== [])
                <div class="eyebrow section-label">{{ $page['topLabel'] }}</div>
                <table class="grid">
                    <tr>
                        @foreach ($page['topPosts'] as $postIndex => $post)
                            @if ($postIndex > 0)<td class="gap3"></td>@endif
                            <td class="col3">
                                <div class="card card-outline post-card">
                                    <div class="post-thumb">@if ($post['image'])<img src="{{ $post['image'] }}" alt="">@endif</div>
                                    <div class="post-body">
                                        <div class="post-title">{{ $post['title'] }}</div>
                                        <div class="post-stats">
                                            @if ($post['stat'] !== null)<b>{{ $post['stat'] }}</b> {{ $post['statLabel'] }}@endif
                                            @if ($post['metric'] !== null)&nbsp;&nbsp;&nbsp;<span><b>{{ $post['metric'] }}</b> {{ $post['metricLabel'] }}</span>@endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        @endforeach
                        @for ($i = count($page['topPosts']); $i < 3; $i++)
                            <td class="gap3"></td><td class="col3"></td>
                        @endfor
                    </tr>
                </table>
            @endif

            @if ($page['insight'] !== '')
                <div class="card card-light insight">
                    <table>
                        <tr>
                            <td class="insight-label"><div class="eyebrow">Insight</div></td>
                            <td class="insight-text">{!! nl2br(e($page['insight'])) !!}</td>
                        </tr>
                    </table>
                </div>
            @endif
        </div>
    @include('pdf.partials.seo-report-footer', ['number' => $number])
@endforeach
</body>
</html>
