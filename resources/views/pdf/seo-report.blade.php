<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} · {{ $customer?->name }}</title>
    <style>
        @foreach ($fonts as $font)
        @font-face { font-family: '{{ $font['family'] }}'; font-style: normal; font-weight: {{ $font['weight'] }}; src: url('{{ $font['path'] }}') format('truetype'); }
        @endforeach

        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            color: #0d1c23;
            background: #ffffff;
            font-family: Inter, DejaVu Sans, sans-serif;
            font-size: 9pt;
            line-height: 1.4;
        }
        table { border-collapse: collapse; }
        td { padding: 0; vertical-align: top; }

        .page { position: relative; width: 210mm; height: 296.8mm; overflow: hidden; page-break-after: always; }
        .page.last { page-break-after: auto; }

        /* Shared header for pages 2–6 */
        .rule { height: 2mm; background: #30b8f0; }
        .band { height: 17.8mm; padding: 5.5mm 12.75mm 0; background: #0d1c23; }
        .band table { width: 100%; }
        .band td { vertical-align: middle; }
        .band-logo { width: 24mm; height: 11.6mm; }
        .band-right { text-align: right; }
        .band-eyebrow { color: #30b8f0; font-size: 7.6pt; font-weight: 700; letter-spacing: .55mm; text-transform: uppercase; }
        .band-title { margin-top: .6mm; color: #ffffff; font-size: 11.8pt; font-weight: 700; }

        .content { padding: 8.5mm 12.75mm 0; }
        .page-title { font-size: 22pt; font-weight: 800; letter-spacing: -.25mm; line-height: 1.1; }
        .page-subtitle { margin-top: 2.2mm; color: #4d626c; font-size: 9.3pt; }

        .eyebrow { color: #4d626c; font-size: 6.9pt; font-weight: 700; letter-spacing: .4mm; text-transform: uppercase; }
        .eyebrow.accent { color: #30b8f0; }
        .section-label { margin: 6.8mm 0 3mm; }

        .footer { position: absolute; left: 12.75mm; right: 12.75mm; bottom: 7.5mm; border-top: .45mm solid #dfe8ec; padding-top: 4.2mm; }
        .footer table { width: 100%; }
        .footer td { color: #4d626c; font-size: 7.6pt; font-weight: 600; }
        .footer .left { width: 35%; font-weight: 700; letter-spacing: .3mm; text-transform: uppercase; }
        .footer .middle { width: 30%; text-align: center; }
        .footer .right { width: 35%; text-align: right; font-weight: 700; letter-spacing: .3mm; }

        /* Cards and grids. Gutters are explicit spacer cells: DomPDF's border-spacing would also pad the outer edges. */
        .grid { width: 100%; table-layout: fixed; }
        .col3 { width: 31.9%; }
        .gap3 { width: 2.15%; }
        .col4 { width: 23.2%; }
        .gap { width: 2.4%; }
        .card { border-radius: 2.6mm; }
        .card-outline { border: .45mm solid #dfe8ec; background: #ffffff; }
        .card-grey { background: #f2f7f9; }
        .card-light { background: #e8f5fc; }
        .card-accent { background: #30b8f0; }
        .card-dark { background: #0d1c23; color: #ffffff; }

        .big-number { font-family: Montserrat, Inter, DejaVu Sans, sans-serif; font-weight: 800; letter-spacing: -.3mm; line-height: 1.05; }

        .pill { display: inline-block; vertical-align: middle; padding: .9mm 2.6mm; border-radius: 3mm; font-size: 8pt; font-weight: 700; white-space: nowrap; }
        .pill-up { background: #e3f3fb; color: #0a6ea6; }
        .pill-down { background: #fdece4; color: #b2471b; }
        .pill-flat { background: #eef3f5; color: #4d626c; }
        .pill-dark { background: #0d1c23; color: #ffffff; }
        .arrow { font-family: DejaVu Sans, sans-serif; font-size: 6pt; }
        .was { vertical-align: middle; padding-left: 2mm; color: #4d626c; font-size: 8.4pt; }

        /* Cover */
        .cover-rule { height: 3.7mm; background: #30b8f0; }
        .cover-hero { position: relative; height: 132mm; padding: 14mm 14.8mm 0; background: #0d1c23; color: #ffffff; }
        .cover-logo { width: 67.5mm; height: 32.5mm; }
        .cover-eyebrow { margin-top: 23mm; color: #30b8f0; font-size: 9pt; font-weight: 700; letter-spacing: .8mm; text-transform: uppercase; }
        .cover-title { margin-top: 3.5mm; font-size: 35pt; font-weight: 800; letter-spacing: -.5mm; line-height: 1.05; }
        .cover-domain { margin-top: 5mm; color: #d4ecf8; font-size: 14pt; font-weight: 500; }
        .cover-period { position: absolute; right: 0; top: 121mm; height: 8.4mm; padding: 3.8mm 9mm 0 7.5mm; border-radius: 6.1mm 0 0 6.1mm; background: #30b8f0; color: #0d1c23; font-size: 11pt; font-weight: 700; line-height: 1; letter-spacing: .1mm; text-transform: uppercase; white-space: nowrap; }
        .cover-period span { color: #ffffff; }
        .cover-body { padding: 10.5mm 12.75mm 0; }
        .cover-card { height: 37mm; padding: 7.5mm 6.8mm 0; }
        .cover-name { margin-top: 3mm; font-size: 15.5pt; font-weight: 700; line-height: 1.15; }
        .cover-contact { margin-top: 2.2mm; color: #34505c; font-size: 9pt; line-height: 1.45; }
        .detail-label { color: #4d626c; font-size: 6.3pt; font-weight: 700; letter-spacing: .35mm; text-transform: uppercase; }
        .detail-value { margin-top: 1.5mm; font-size: 11pt; font-weight: 700; line-height: 1.15; }
        .compared { margin-top: 5mm; color: #34505c; font-size: 8.8pt; }
        .compared b { color: #0d1c23; font-weight: 700; }
        .glance-card { padding: 6.5mm 6.2mm 6mm; }
        .glance-label { font-size: 8.8pt; font-weight: 600; color: #26414c; }
        .card-accent .glance-label { color: #0d1c23; }
        .glance-value { margin: 2.6mm 0 3.4mm; font-size: 26pt; }

        /* Executive summary */
        .brief { margin-top: 7.3mm; padding: 6.2mm 6.4mm 6.4mm; }
        .brief-text { margin-top: 2.6mm; color: #1b2f38; font-size: 9.8pt; line-height: 1.55; }
        .metric-card { padding: 5.4mm 5.6mm 5mm; }
        .metric-label { font-size: 8.6pt; font-weight: 600; color: #26414c; }
        .metric-value { margin: 1.4mm 0 2.6mm; font-size: 23pt; }
        .card-accent .metric-label { color: #0d1c23; }
        .card-accent .was { color: #0d1c23; }
        .wins { margin-top: 6.5mm; padding: 7.5mm 7.5mm 4mm; }
        .win { width: 100%; margin-top: 5mm; }
        .win-num { width: 12mm; }
        .num-dot { width: 7.8mm; height: 5.9mm; padding-top: 1.9mm; border-radius: 3.9mm; background: #30b8f0; color: #0d1c23; text-align: center; font-size: 9pt; font-weight: 700; line-height: 1; }
        .win-title { font-size: 11.2pt; font-weight: 700; color: #ffffff; line-height: 1.2; }
        .win-detail { margin-top: 1.5mm; color: #c6d3d9; font-size: 9pt; }

        /* Organic traffic */
        .chart-card { margin-top: 7.3mm; padding: 6.3mm 6.3mm 4.5mm; }
        .chart-head { width: 100%; }
        .chart-growth { margin-top: 1.6mm; font-size: 9.3pt; color: #26414c; }
        .chart-growth b { color: #0d1c23; font-weight: 700; }
        .chart-total { text-align: right; }
        .chart-total-value { font-size: 19pt; }
        .chart-total-label { color: #4d626c; font-size: 8.4pt; }
        .bars { width: 100%; margin-top: 4mm; table-layout: fixed; }
        .bars td { height: 42mm; vertical-align: bottom; padding: 0 1.45mm; }
        .bar { border-radius: 1.4mm 1.4mm 0 0; }
        .bar-past { background: #bfe5f7; }
        .bar-previous { background: #7fd0f4; }
        .bar-current { background: #00a3ff; }
        .bar-labels { width: 100%; table-layout: fixed; border-top: .35mm solid #dfe8ec; }
        .bar-labels td { padding-top: 1.6mm; text-align: center; color: #4d626c; font-size: 7.6pt; font-weight: 600; }
        .bar-labels td.current { color: #0d1c23; font-weight: 700; }

        .table-head { border-radius: 2.6mm; background: #0d1c23; }
        .table-head table, .data-table { width: 100%; table-layout: fixed; }
        .table-head td { height: 9.8mm; padding: 0 4.2mm; vertical-align: middle; color: #ffffff; font-size: 7.4pt; font-weight: 700; letter-spacing: .3mm; text-transform: uppercase; white-space: nowrap; }
        .data-table td { height: 9.4mm; padding: 0 4.2mm; vertical-align: middle; border-bottom: .35mm solid #dfe8ec; font-size: 9.6pt; }
        .data-table td.name { font-weight: 600; }
        .r { text-align: right; }
        .c { text-align: center; }

        /* Keyword rankings */
        .bucket { padding: 5.5mm 4.8mm 5mm; }
        .bucket-label { font-size: 8.6pt; font-weight: 600; color: #26414c; }
        .card-accent .bucket-label { color: #0d1c23; }
        .bucket-value { margin: 2.2mm 0 1.6mm; font-size: 23pt; }
        .bucket-change { font-size: 8pt; font-weight: 700; color: #6b7f88; }
        .card-accent .bucket-change { color: #0d1c23; }
        .card-light .bucket-change { color: #0a6ea6; }
        .kw-table td { height: 9.5mm; }
        .kw-table td.name { font-size: 9.6pt; }
        .pos { display: inline-block; width: 8mm; padding: .9mm 0; border-radius: 1.3mm; text-align: center; font-weight: 700; font-size: 9.4pt; }
        .pos-accent { background: #30b8f0; color: #0d1c23; }
        .pos-light { background: #e8f5fc; color: #0d1c23; }
        .pos-muted { background: #f2f7f9; color: #0d1c23; }
        .note { margin-top: 6.5mm; padding: 6.2mm 6.4mm 6.4mm; }
        .note-text { margin-top: 3mm; color: #1b2f38; font-size: 9.8pt; line-height: 1.6; }

        /* Technical & authority */
        .health { height: 48.2mm; padding: 6.3mm 5.8mm 0; }
        .health-ring { width: 28.5mm; height: 28.5mm; }
        .health-ring img { display: block; width: 28.5mm; height: 28.5mm; }
        /* Pulled up over the ring: DomPDF misplaces absolutely positioned boxes inside table cells. */
        .health-score { margin-top: -19.6mm; width: 28.5mm; text-align: center; }
        .health-score .big-number { font-size: 19pt; color: #ffffff; line-height: 1; }
        .health-score .out-of { margin-top: .4mm; color: #c6d3d9; font-size: 6.4pt; }
        .health-last { padding-left: 4.5mm; vertical-align: middle; }
        .health-last .label { color: #c6d3d9; font-size: 9.4pt; }
        .health-last .big-number { margin-top: 1mm; color: #ffffff; font-size: 14pt; }
        .vitals { height: 48.5mm; padding: 6mm 5.8mm 0; }
        .vitals-head { width: 100%; }
        .vital { width: 100%; border-bottom: .35mm solid #dfe8ec; }
        .vital td { height: 12.2mm; vertical-align: middle; }
        .vital.last { border-bottom: none; }
        .vital-label { font-size: 9.4pt; font-weight: 700; line-height: 1.15; }
        .vital-help { color: #4d626c; font-size: 7.8pt; }
        .vital-value { width: 22mm; text-align: right; padding-right: 3mm; font-size: 11.2pt; font-weight: 700; }
        .vital-rating { width: 22mm; text-align: right; }
        .rating { display: inline-block; padding: .9mm 2.4mm; border-radius: 3mm; font-size: 8pt; font-weight: 700; white-space: nowrap; }
        .rating-good { background: #e3f3fb; color: #0a6ea6; }
        .rating-needs { background: #fdece4; color: #b2471b; }
        .rating-poor { background: #fbe1e1; color: #b42323; }
        .fix { height: 23.5mm; padding: 5mm 4.8mm 0; }
        .fix-value { font-size: 20pt; }
        .fix-label { margin-top: 2mm; color: #26414c; font-size: 9.2pt; line-height: 1.35; }
        .auth { padding: 4.8mm 5.6mm 5mm; }
        .auth-label { font-size: 9pt; font-weight: 600; color: #26414c; }
        .auth-value { margin: 1.2mm 0 1.2mm; font-size: 21pt; }
        .auth-change { font-size: 8.4pt; font-weight: 700; }
        .tone-up { color: #0a6ea6; }
        .tone-down { color: #b2471b; }
        .tone-flat { color: #4d626c; }
        .links-table td { height: 9.5mm; }

        /* Work & next steps */
        .work { width: 100%; border-bottom: .35mm solid #dfe8ec; }
        .work td { padding: 3.2mm 0 3.3mm; }
        .work.first td { padding-top: 0; }
        .work-tag-cell { width: 27mm; }
        .work-tag { width: 22.8mm; padding: 1.1mm 0; border-radius: 2.6mm; background: #e8f5fc; color: #0a6ea6; text-align: center; font-size: 7.4pt; font-weight: 700; letter-spacing: .35mm; text-transform: uppercase; }
        .work-title { font-size: 10.2pt; font-weight: 700; line-height: 1.25; }
        .work-detail { margin-top: 1mm; color: #34505c; font-size: 9pt; }
        .priorities { margin-top: 7mm; padding: 6.3mm 6.3mm 3.5mm; }
        .priority { width: 100%; margin-top: 3.4mm; }
        .priority td { vertical-align: middle; }
        .priority-num { width: 11mm; }
        .priority .num-dot { background: #0d1c23; color: #ffffff; }
        .priority-text { font-size: 10pt; font-weight: 700; }
        .contact { margin-top: 5.4mm; padding: 6.8mm 7.4mm 6.4mm; }
        .contact table { width: 100%; }
        .contact-name { margin-top: 2.2mm; font-size: 14pt; font-weight: 700; }
        .contact-text { margin-top: 1.4mm; color: #c6d3d9; font-size: 9.4pt; }
        .contact-right { text-align: right; vertical-align: middle; }
        .contact-phone { font-size: 13pt; font-weight: 700; }
        .contact-email { margin-top: 1.4mm; color: #30b8f0; font-size: 11pt; font-weight: 700; }
        .empty { margin-top: 4mm; color: #6b7f88; font-size: 9pt; }
    </style>
</head>
<body>
@php
    $sections = [
        2 => 'Executive summary',
        3 => 'Organic traffic',
        4 => 'Keyword rankings',
        5 => 'Technical & authority',
        6 => 'Work & next steps',
    ];
@endphp

{{-- Page 1: cover --}}
<div class="page">
    <div class="cover-rule"></div>
    <div class="cover-hero">
        @if ($logo)
            <img class="cover-logo" src="{{ $logo }}" alt="Web Stamp">
        @endif
        <div class="cover-eyebrow">SEO Performance Report</div>
        <div class="cover-title">{{ $title }}</div>
        <div class="cover-domain">{{ $report->website_domain }}</div>
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
                                <td style="width: 28%;">
                                    @if ($campaign)
                                        <div class="detail-label">Month</div><div class="detail-value">{{ $campaign }}</div>
                                    @endif
                                </td>
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
                @foreach ([['card' => $cards['sessions'], 'style' => 'card-outline'], ['card' => $cards['topTen'], 'style' => 'card-outline', 'suffix' => ' keywords'], ['card' => $cards['enquiries'], 'style' => 'card-accent']] as $index => $item)
                    @if ($index > 0)<td class="gap3"></td>@endif
                    <td class="col3">
                        <div class="card {{ $item['style'] }} glance-card">
                            <div class="glance-label">{{ $item['card']['label'] }}</div>
                            <div class="big-number glance-value">{{ $item['card']['value'] ?? '–' }}</div>
                            @if ($item['card']['change'])
                                <span class="pill {{ $item['style'] === 'card-accent' ? 'pill-dark' : 'pill-'.$item['card']['change']['tone'] }}">@if ($item['card']['change']['arrow'])<span class="arrow">{{ $item['card']['change']['arrow'] }}</span>&nbsp; @endif{{ $item['card']['change']['text'] }}{{ ($item['suffix'] ?? '') !== '' && $item['card']['change']['tone'] !== 'flat' ? $item['suffix'] : '' }}</span>
                            @endif
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

    <div class="footer">
        <table><tr>
            <td class="left">web-stamp.co.uk</td>
            <td class="right" style="width: 65%; letter-spacing: 0;">{{ $preparedBy['phone'] }} · {{ $preparedBy['email'] }}</td>
        </tr></table>
    </div>
</div>

{{-- Page 2: executive summary --}}
@include('pdf.partials.seo-report-header', ['number' => 2, 'section' => $sections[2]])
    <div class="content">
        <div class="page-title">Executive summary</div>
        <div class="page-subtitle">{{ $periodLabel }} compared with {{ $previousMonthLabel }}</div>

        @if ($summary !== '')
            <div class="card card-grey brief">
                <div class="eyebrow">This month in brief</div>
                <div class="brief-text">{!! nl2br(e($summary)) !!}</div>
            </div>
        @endif

        @foreach (array_chunk(array_values($cards), 3) as $rowIndex => $row)
            <table class="grid" style="margin-top: {{ $rowIndex === 0 ? ($summary !== '' ? '5.4mm' : '7.3mm') : '3.6mm' }};">
                <tr>
                    @foreach ($row as $index => $card)
                        @php $accent = $rowIndex === 1 && $index === 2; @endphp
                        @if ($index > 0)<td class="gap3"></td>@endif
                        <td class="col3">
                            <div class="card {{ $accent ? 'card-accent' : 'card-outline' }} metric-card">
                                <div class="metric-label">{{ $card['label'] }}</div>
                                <div class="big-number metric-value">{{ $card['value'] ?? '–' }}</div>
                                @if ($card['change'])
                                    <span class="pill {{ $accent ? 'pill-dark' : 'pill-'.$card['change']['tone'] }}">@if ($card['change']['arrow'])<span class="arrow">{{ $card['change']['arrow'] }}</span>&nbsp; @endif{{ $card['change']['text'] }}</span>
                                @endif
                                @if ($card['was'] !== null)<span class="was">was {{ $card['was'] }}</span>@endif
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endforeach

        @if ($keyWins !== [])
            <div class="card card-dark wins">
                <div class="eyebrow accent">Key wins this month</div>
                @foreach ($keyWins as $index => $win)
                    <table class="win">
                        <tr>
                            <td class="win-num"><div class="num-dot">{{ $index + 1 }}</div></td>
                            <td>
                                <div class="win-title">{{ $win['title'] }}</div>
                                @if (trim((string) ($win['detail'] ?? '')) !== '')
                                    <div class="win-detail">{{ $win['detail'] }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                @endforeach
            </div>
        @endif
    </div>
@include('pdf.partials.seo-report-footer', ['number' => 2])

{{-- Page 3: organic traffic --}}
@include('pdf.partials.seo-report-header', ['number' => 3, 'section' => $sections[3]])
    <div class="content">
        <div class="page-title">Organic traffic</div>
        <div class="page-subtitle">Visits from unpaid Google search results, plus performance data from Google Search Console</div>

        <div class="card card-outline chart-card">
            <table class="chart-head">
                <tr>
                    <td>
                        <div class="eyebrow">Organic sessions · last 12 months</div>
                        @if ($growthSinceStart)
                            <div class="chart-growth">Since the campaign started: <b>{{ $growthSinceStart }}</b></div>
                        @endif
                    </td>
                    <td class="chart-total">
                        @if ($sessionsTotal !== null)
                            <div class="big-number chart-total-value">{{ $sessionsTotal }}</div>
                            <div class="chart-total-label">this month</div>
                        @endif
                    </td>
                </tr>
            </table>
            <table class="bars">
                <tr>
                    @foreach ($chart as $bar)
                        <td>@if ($bar['height'] > 0)<div class="bar bar-{{ $bar['shade'] }}" style="height: {{ $bar['height'] }}mm;"></div>@endif</td>
                    @endforeach
                </tr>
            </table>
            <table class="bar-labels">
                <tr>
                    @foreach ($chart as $bar)
                        <td class="{{ $bar['shade'] === 'current' ? 'current' : '' }}">{{ $bar['label'] }}</td>
                    @endforeach
                </tr>
            </table>
        </div>

        @if ($searchConsole !== [])
            <div class="eyebrow section-label">Google Search Console</div>
            <div class="table-head">
                <table><tr>
                    <td style="width: 40%;">Metric</td>
                    <td class="r" style="width: 20%;">This month</td>
                    <td class="r" style="width: 24%;">Last month</td>
                    <td class="r" style="width: 16%;">Change</td>
                </tr></table>
            </div>
            <table class="data-table">
                @foreach ($searchConsole as $row)
                    <tr>
                        <td class="name" style="width: 40%;">{{ $row['label'] }}</td>
                        <td class="r" style="width: 20%;">{{ $row['current'] }}</td>
                        <td class="r" style="width: 24%;">{{ $row['previous'] ?? '–' }}</td>
                        <td class="r" style="width: 16%;">@if ($row['change'])<span class="pill pill-{{ $row['change']['tone'] }}">{{ $row['change']['text'] }}</span>@endif</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if ($landingPages !== [])
            <div class="eyebrow section-label">Top landing pages from search</div>
            <div class="table-head">
                <table><tr>
                    <td style="width: 60%;">Page</td>
                    <td class="r" style="width: 22%;">Sessions</td>
                    <td class="r" style="width: 18%;">Change</td>
                </tr></table>
            </div>
            <table class="data-table">
                @foreach ($landingPages as $page)
                    <tr>
                        <td class="name" style="width: 60%;">{{ $page['path'] }}</td>
                        <td class="r" style="width: 22%;">{{ $page['sessions'] }}</td>
                        <td class="r" style="width: 18%;">@if ($page['change'])<span class="pill pill-{{ $page['change']['tone'] }}">{{ $page['change']['text'] }}</span>@endif</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
@include('pdf.partials.seo-report-footer', ['number' => 3])

{{-- Page 4: keyword rankings --}}
@include('pdf.partials.seo-report-header', ['number' => 4, 'section' => $sections[4]])
    <div class="content">
        <div class="page-title">Keyword rankings</div>
        <div class="page-subtitle">Where the site appears in Google for the {{ $trackedKeywords !== null ? number_format($trackedKeywords).' ' : '' }}search terms we track</div>

        <table class="grid" style="margin-top: 7.3mm;">
            <tr>
                @foreach ($buckets as $index => $bucket)
                    @if ($index > 0)<td class="gap"></td>@endif
                    <td class="col4">
                        <div class="card card-{{ $bucket['style'] === 'muted' ? 'grey' : $bucket['style'] }} bucket">
                            <div class="bucket-label">{{ $bucket['label'] }}</div>
                            <div class="big-number bucket-value">{{ $bucket['value'] }}</div>
                            @if ($bucket['change'])<div class="bucket-change">{{ $bucket['change'] }}</div>@endif
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>

        @if ($keywords !== [])
            <div class="eyebrow section-label">Priority keywords</div>
            <div class="table-head">
                <table><tr>
                    <td style="width: 32%;">Keyword</td>
                    <td class="c" style="width: 22%;">Monthly searches</td>
                    <td class="c" style="width: 16%;">Last month</td>
                    <td class="c" style="width: 16%;">This month</td>
                    <td class="c" style="width: 14%;">Change</td>
                </tr></table>
            </div>
            <table class="data-table kw-table">
                @foreach ($keywords as $row)
                    <tr>
                        <td class="name" style="width: 32%;">{{ $row['keyword'] }}</td>
                        <td class="c" style="width: 22%;">{{ $row['searches'] }}</td>
                        <td class="c" style="width: 16%;">{{ $row['previous'] }}</td>
                        <td class="c" style="width: 16%;"><span class="pos pos-{{ $row['band'] }}">{{ $row['current'] }}</span></td>
                        <td class="c" style="width: 14%;"><span class="pill pill-{{ $row['change']['tone'] }}">@if (str_starts_with($row['change']['text'], '▲') || str_starts_with($row['change']['text'], '▼'))<span class="arrow">{{ mb_substr($row['change']['text'], 0, 1) }}</span>{{ mb_substr($row['change']['text'], 1) }}@else{{ $row['change']['text'] }}@endif</span></td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if ($rankingsNote !== '')
            <div class="card card-light note">
                <div class="eyebrow">What this means</div>
                <div class="note-text">{!! nl2br(e($rankingsNote)) !!}</div>
            </div>
        @endif
    </div>
@include('pdf.partials.seo-report-footer', ['number' => 4])

{{-- Page 5: technical & authority --}}
@include('pdf.partials.seo-report-header', ['number' => 5, 'section' => $sections[5]])
    <div class="content">
        <div class="page-title">Technical health &amp; authority</div>
        <div class="page-subtitle">How easily Google can crawl the site, how fast it loads, and how much other websites trust it</div>

        @if ($health || $vitals)
            <table class="grid" style="margin-top: 7.3mm;">
                <tr>
                    <td style="width: 35.8%;">
                        @if ($health)
                            <div class="card card-dark health">
                                <div class="eyebrow accent">Site health score</div>
                                <div style="height: 4.8mm;"></div>
                                <table>
                                    <tr>
                                        <td>
                                            <div class="health-ring">
                                                <img src="{{ $health['ring'] }}" alt="">
                                                <div class="health-score">
                                                    <div class="big-number">{{ $health['score'] }}</div>
                                                    <div class="out-of">out of 100</div>
                                                </div>
                                            </div>
                                        </td>
                                        @if ($health['previous'] !== null)
                                            <td class="health-last">
                                                <div class="label">Last month</div>
                                                <div class="big-number">{{ $health['previous'] }}</div>
                                            </td>
                                        @endif
                                    </tr>
                                </table>
                            </div>
                        @endif
                    </td>
                    <td style="width: 2.4%;"></td>
                    <td style="width: 61.8%;">
                        @if ($vitals)
                            <div class="card card-outline vitals">
                                <table class="vitals-head">
                                    <tr>
                                        <td class="eyebrow" style="vertical-align: middle;">Core Web Vitals · Mobile</td>
                                        <td class="r">@if ($vitals['status'])<span class="rating rating-{{ $vitals['passed'] ? 'good' : 'needs' }}">{{ $vitals['status'] }}</span>@endif</td>
                                    </tr>
                                </table>
                                <div style="margin-top: 1.8mm;">
                                    @foreach ($vitals['rows'] as $index => $row)
                                        <table class="vital {{ $loop->last ? 'last' : '' }}">
                                            <tr>
                                                <td>
                                                    <div class="vital-label">{{ $row['label'] }}</div>
                                                    <div class="vital-help">{{ $row['help'] }}</div>
                                                </td>
                                                <td class="vital-value">{{ $row['value'] }}</td>
                                                <td class="vital-rating"><span class="rating rating-{{ $row['rating'] }}">{{ $row['ratingLabel'] }}</span></td>
                                            </tr>
                                        </table>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </td>
                </tr>
            </table>
        @endif

        @if ($fixes !== [])
            <div class="eyebrow section-label">Issues fixed this month</div>
            <table class="grid">
                <tr>
                    @foreach ($fixes as $index => $fix)
                        @if ($index > 0)<td class="gap"></td>@endif
                        <td class="col4">
                            <div class="card card-grey fix">
                                <div class="big-number fix-value">{{ $fix['count'] }}</div>
                                <div class="fix-label">{{ $fix['label'] }}</div>
                            </div>
                        </td>
                    @endforeach
                    @for ($i = count($fixes); $i < 4; $i++)
                        <td class="gap"></td><td class="col4"></td>
                    @endfor
                </tr>
            </table>
        @endif

        @if ($authority !== [])
            <div class="eyebrow section-label">Backlinks &amp; authority</div>
            <table class="grid">
                <tr>
                    @foreach ($authority as $index => $card)
                        @if ($index > 0)<td class="gap3"></td>@endif
                        <td class="col3">
                            <div class="card card-outline auth">
                                <div class="auth-label">{{ $card['label'] }}</div>
                                <div class="big-number auth-value">{{ $card['value'] }}</div>
                                @if ($card['change'])<div class="auth-change tone-{{ $card['change']['tone'] }}">{{ $card['change']['text'] }}</div>@endif
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif

        @if ($newLinks !== [])
            <div class="table-head" style="margin-top: 4.2mm;">
                <table><tr>
                    <td style="width: 52%;">New links earned</td>
                    <td class="r" style="width: 18%;">Authority</td>
                    <td class="r" style="width: 30%;">Type</td>
                </tr></table>
            </div>
            <table class="data-table links-table">
                @foreach ($newLinks as $link)
                    <tr>
                        <td class="name" style="width: 52%;">{{ $link['domain'] }}</td>
                        <td class="r" style="width: 18%; padding-right: 8mm;">{{ $link['authority'] }}</td>
                        <td class="r" style="width: 30%;">{{ $link['type'] }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
@include('pdf.partials.seo-report-footer', ['number' => 5])

{{-- Page 6: work & next steps --}}
@include('pdf.partials.seo-report-header', ['number' => 6, 'section' => $sections[6]])
    <div class="content">
        <div class="page-title">Work completed this month</div>
        <div class="page-subtitle">Everything WebStamp delivered on your SEO campaign in {{ \Illuminate\Support\Carbon::parse($report->report_month)->format('F') }}</div>

        @if ($workItems !== [])
            <div style="margin-top: 6.5mm;">
                @foreach ($workItems as $index => $item)
                    <table class="work {{ $index === 0 ? 'first' : '' }}">
                        <tr>
                            <td class="work-tag-cell">
                                @if (trim((string) ($item['category'] ?? '')) !== '')
                                    <div class="work-tag">{{ $item['category'] }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="work-title">{{ $item['title'] }}</div>
                                @if (trim((string) ($item['detail'] ?? '')) !== '')
                                    <div class="work-detail">{{ $item['detail'] }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                @endforeach
            </div>
        @else
            <div class="empty">No work items were added for this month.</div>
        @endif

        @if ($priorities !== [])
            <div class="card card-light priorities">
                <div class="eyebrow">Next month's priorities</div>
                @foreach ($priorities as $index => $priority)
                    <table class="priority">
                        <tr>
                            <td class="priority-num"><div class="num-dot">{{ $index + 1 }}</div></td>
                            <td class="priority-text">{{ $priority }}</td>
                        </tr>
                    </table>
                @endforeach
            </div>
        @endif

        <div class="card card-dark contact">
            <table>
                <tr>
                    <td>
                        <div class="eyebrow accent">Questions about your report?</div>
                        <div class="contact-name">{{ $preparedBy['name'] }} · Web Stamp</div>
                        <div class="contact-text">Happy to talk through any of it. Just get in touch.</div>
                    </td>
                    <td class="contact-right">
                        <div class="contact-phone">{{ $preparedBy['phone'] }}</div>
                        <div class="contact-email">{{ $preparedBy['email'] }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
@include('pdf.partials.seo-report-footer', ['number' => 6, 'last' => true])
</body>
</html>
