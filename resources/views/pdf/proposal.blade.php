<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Proposal {{ $proposal->proposal_number }} v{{ $proposal->version }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; width: 210mm; min-height: 297mm; }
        body {
            color: #0b1d24;
            background: #ffffff;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 9pt;
            line-height: 1.5;
        }
        .page { width: 210mm; min-height: 297mm; background: #ffffff; }
        .top-rule { height: 4.7mm; background: #30b8f0; }
        .hero { padding: 8mm 12.6mm 9mm; background: #0d1c23; color: #ffffff; }
        .hero-table { width: 100%; border-collapse: collapse; }
        .hero-left { width: 55%; vertical-align: top; }
        .hero-right { width: 45%; vertical-align: top; text-align: right; }
        .brand-crop { position: relative; width: 43.5mm; height: 24.2mm; margin-top: 2mm; }
        .brand-crop .brand-image { display: block; width: 43.5mm; height: 24.2mm; }
        .brand-crop .brand-stamp {
            position: absolute; width: 10.65mm; height: 9mm; left: 31.3mm; top: -2.4mm;
            transform: rotate(8.3deg); transform-origin: top left;
        }
        .brand-fallback { width: 46mm; line-height: .72; font-weight: 800; letter-spacing: -.9mm; }
        .brand-fallback .web { display: inline-block; color: #30b8f0; font-size: 25pt; }
        .brand-fallback .fallback-mark {
            display: inline-block; width: 9mm; height: 9mm; margin-left: 2mm;
            border: .8mm solid #ffffff; border-radius: 1.2mm; vertical-align: top; transform: rotate(7deg);
        }
        .brand-fallback .stamp { display: block; color: #ffffff; font-size: 25pt; }
        .doc-label { font-size: 16pt; font-weight: 800; letter-spacing: .3mm; }
        .doc-number { margin-top: 2mm; color: #aebdc5; font-size: 9.5pt; font-weight: 700; }
        .pill-row { margin-top: 2.6mm; }
        .pill {
            display: inline-block; padding: 1.4mm 3.6mm; border-radius: 6mm;
            font-size: 6.6pt; font-weight: 800; text-transform: uppercase; letter-spacing: .3mm;
            border-width: .3mm; border-style: solid;
        }
        .version-pill { margin-left: 2mm; background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.3); color: #ffffff; }

        .content { padding: 10mm 12.6mm 0; }
        .doc-title { margin: 0; font-size: 19pt; font-weight: 800; color: #0b1d24; line-height: 1.15; }
        .doc-subtitle { margin-top: 2mm; color: #465d68; font-size: 9.5pt; }

        .info-card { margin-top: 7mm; padding: 5.5mm 7mm; border-radius: 2.8mm; background: #e9f6fc; page-break-inside: avoid; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-left { width: 58%; vertical-align: top; }
        .info-right { width: 42%; vertical-align: top; text-align: right; }
        .eyebrow { color: #4f7f95; font-size: 6.6pt; font-weight: 800; text-transform: uppercase; letter-spacing: .3mm; }
        .customer-name { margin-top: 1.8mm; font-size: 12.5pt; line-height: 1.1; font-weight: 800; color: #0b1d24; }
        .customer-contact { margin-top: 1.6mm; color: #34515e; font-size: 7.3pt; }
        .customer-need { margin-top: 1.6mm; color: #34515e; font-size: 7.3pt; }
        .date-block + .date-block { margin-top: 3.4mm; }
        .date-label { color: #4f7f95; font-size: 6.4pt; font-weight: 800; text-transform: uppercase; letter-spacing: .3mm; }
        .date-value { margin-top: 1mm; font-size: 9pt; font-weight: 800; color: #0b1d24; }

        .section { margin-top: 8mm; }
        .section-heading { font-size: 12.5pt; font-weight: 800; color: #0b1d24; }
        .section-subtext { margin-top: 1.4mm; color: #6a7d86; font-size: 8pt; }

        .text-card { margin-top: 4mm; padding: 5mm 6mm; border-radius: 2.8mm; background: #f4f8fa; border: .25mm solid #e1eaee; }
        .text-card p { margin: 0 0 3mm; color: #263c45; font-size: 8.6pt; line-height: 1.6; }
        .text-card p:last-child { margin-bottom: 0; }
        .text-card p b { color: #0b1d24; }

        .details-table { width: 100%; border-collapse: collapse; margin-top: 4mm; border-radius: 2.8mm; overflow: hidden; }
        .details-table td { padding: 3.4mm 5mm; font-size: 8.4pt; }
        .details-table .row-even { background: #f4f8fa; }
        .details-table .row-odd { background: #ffffff; }
        .details-table .d-label { color: #46606b; }
        .details-table .d-value { text-align: right; font-weight: 800; color: #0b1d24; }

        .items-shell { margin-top: 4mm; width: 100%; }
        .items-header { overflow: hidden; border-radius: 2.7mm; background: #0d1c23; color: #ffffff; }
        .items-header table { width: 100%; border-collapse: collapse; }
        .items-header td { height: 11mm; padding: 0 5mm; vertical-align: middle; font-size: 6.4pt; font-weight: 800; text-transform: uppercase; white-space: nowrap; }
        .items-table { table-layout: fixed; width: 100%; border-collapse: collapse; }
        .items-table td { padding: 3.8mm 5mm; border-bottom: .5mm solid #e1eaee; vertical-align: top; font-size: 8.6pt; }
        .items-table td.numeric { text-align: right; }
        .items-table tr:last-child td { border-bottom: none; }

        .total-wrap { margin-top: 4mm; width: 100%; }
        .total-card { float: right; width: 68mm; padding: 5mm 6mm; border-radius: 2.8mm; background: #30b8f0; color: #06171e; }
        .mini-totals { width: 100%; border-collapse: collapse; }
        .mini-totals td { padding: 0 0 1.8mm; font-size: 7.8pt; }
        .mini-totals td:last-child { text-align: right; font-weight: 800; }
        .total-rule { height: .45mm; margin: .5mm 0 3mm; background: rgba(8, 68, 92, .22); }
        .total-due-table { width: 100%; border-collapse: collapse; }
        .total-due-label { font-size: 7.8pt; font-weight: 800; text-transform: uppercase; vertical-align: bottom; }
        .total-due-amount { text-align: right; font-size: 15pt; line-height: 1; font-weight: 800; }
        .clear { clear: both; }

        .cta-panel {
            margin-top: 9mm; padding: 7mm 7.5mm; border-radius: 2.8mm; background: #0d1c23; color: #ffffff;
            page-break-inside: avoid;
        }
        .cta-table { width: 100%; border-collapse: collapse; }
        .cta-left { width: 68%; vertical-align: middle; }
        .cta-right { width: 32%; vertical-align: middle; text-align: right; }
        .cta-heading { font-size: 11pt; font-weight: 800; }
        .cta-text { margin-top: 1.8mm; color: #b7c5cb; font-size: 7.8pt; line-height: 1.6; }
        .cta-button {
            display: inline-block; padding: 3mm 5.5mm; border-radius: 6mm; background: #30b8f0;
            color: #06171e; font-size: 8pt; font-weight: 800; text-decoration: none;
        }

        .footer { margin-top: 8mm; padding: 5mm 12.6mm 8mm; page-break-inside: avoid; }
        .footer-rule { height: .5mm; background: #e1eaee; }
        .footer-table { width: 100%; border-collapse: collapse; margin-top: 4mm; }
        .footer-table td { color: #6a7d86; font-size: 7pt; }
        .footer-table td:first-child { font-weight: 800; text-transform: uppercase; }
        .footer-table td:last-child { text-align: right; }
        a { color: inherit; text-decoration: none; }

        .brand-fallback, .doc-label, .doc-number, .doc-title, .pill, .eyebrow, .customer-name,
        .date-label, .date-value, .section-heading, .items-header td,
        .d-value, .total-due-label, .total-due-amount, .cta-heading, .cta-button,
        .footer-table td:first-child {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-weight: bold;
        }
    </style>
</head>
<body>
@php
    $logoDataUri = null;
    $stampDataUri = null;
    $logoPath = resource_path('images/white-logo2.svg');
    if (is_file($logoPath)) {
        $svg = file_get_contents($logoPath);
        if ($svg !== false && $svg !== '') {
            $logoDataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);
            if (preg_match('/<image[^>]+xlink:href="(data:image\/png;base64,[^"]+)"/', $svg, $matches) === 1) {
                $stampDataUri = $matches[1];
            }
        }
    }

    $status = $proposal->effectiveStatus();
    $statusColors = [
        'pending' => ['bg' => 'rgba(48,184,240,.16)', 'border' => '#30b8f0', 'text' => '#ffffff'],
        'sent' => ['bg' => 'rgba(48,184,240,.16)', 'border' => '#30b8f0', 'text' => '#ffffff'],
        'approved' => ['bg' => 'rgba(52,211,153,.18)', 'border' => '#34d399', 'text' => '#ffffff'],
        'declined' => ['bg' => 'rgba(248,113,113,.18)', 'border' => '#f87171', 'text' => '#ffffff'],
        'expired' => ['bg' => 'rgba(148,163,184,.2)', 'border' => '#94a3b8', 'text' => '#e2e8f0'],
    ];
    $statusStyle = $statusColors[$status] ?? $statusColors['pending'];

    // Free-text notes/terms are written as blank-line-separated paragraphs, each
    // optionally starting with a short "Label:" lead-in (e.g. "Hosting: ..."),
    // matching how these fields are actually authored today. Rendering that
    // structure properly (instead of one flat paragraph) is most of what makes
    // this look designed rather than dumped.
    $formatText = function (?string $text): array {
        $text = trim((string) $text);
        if ($text === '') {
            return [];
        }

        return array_values(array_filter(array_map(function (string $paragraph): string {
            $paragraph = trim($paragraph);
            $paragraph = e($paragraph);
            $paragraph = preg_replace('/^([A-Z][A-Za-z0-9 &\/]{1,28}:)/', '<b>$1</b>', $paragraph, 1);

            return nl2br($paragraph);
        }, preg_split('/\n\s*\n/', $text)), fn ($p) => $p !== ''));
    };

    $briefParagraphs = $formatText($proposal->notes);
    $termsParagraphs = $formatText($proposal->terms);
    $hasAnswers = is_array($proposal->form_answers) && count($proposal->form_answers) > 0;
@endphp

<div class="page">
    <div class="top-rule"></div>
    <div class="hero">
        <table class="hero-table">
            <tr>
                <td class="hero-left">
                    @if ($logoDataUri)
                        <div class="brand-crop">
                            <img class="brand-image" src="{{ $logoDataUri }}" alt="Web Stamp">
                            @if ($stampDataUri)
                                <img class="brand-stamp" src="{{ $stampDataUri }}" alt="">
                            @endif
                        </div>
                    @else
                        <div class="brand-fallback"><span class="web">WEB</span><span class="fallback-mark">&nbsp;</span><span class="stamp">STAMP</span></div>
                    @endif
                </td>
                <td class="hero-right">
                    <div class="doc-label">PROPOSAL</div>
                    <div class="doc-number">{{ $proposal->proposal_number }}</div>
                    <div class="pill-row">
                        <span class="pill" style="background: {{ $statusStyle['bg'] }}; border-color: {{ $statusStyle['border'] }}; color: {{ $statusStyle['text'] }};">{{ $status }}</span>
                        <span class="pill version-pill">Version {{ $proposal->version }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <main class="content">
        <div class="doc-title">Your project proposal</div>
        @if (trim((string) $proposal->title) !== '')
            <div class="doc-subtitle">{{ $proposal->title }}</div>
        @endif

        <div class="info-card">
            <table class="info-table">
                <tr>
                    <td class="info-left">
                        <div class="eyebrow">Proposal for</div>
                        <div class="customer-name">{{ $customer?->name }}</div>
                        @if ($customer?->email)
                            <div class="customer-contact"><a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a></div>
                        @endif
                        @if ($customer?->phone)
                            <div class="customer-contact"><a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a></div>
                        @endif
                        @if (trim((string) $proposal->proposal_type_label) !== '')
                            <div class="customer-need">Need: {{ $proposal->proposal_type_label }}</div>
                        @endif
                    </td>
                    <td class="info-right">
                        <div class="date-block">
                            <div class="date-label">Issued</div>
                            <div class="date-value">{{ $proposal->issue_date?->format('M j, Y') }}</div>
                        </div>
                        <div class="date-block">
                            <div class="date-label">Valid until</div>
                            <div class="date-value">{{ $proposal->expiry_date?->format('M j, Y') }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        @if ($hasAnswers)
            <section class="section">
                <div class="section-heading">Proposal details</div>
                <table class="details-table">
                    @foreach ($proposal->form_answers as $index => $answer)
                        @php
                            $answerValue = $answer['value'] ?? null;
                            if (is_bool($answerValue)) {
                                $answerValue = $answerValue ? 'Yes' : 'No';
                            }
                            if ($answerValue === null || $answerValue === '') {
                                $answerValue = 'Not specified';
                            }
                        @endphp
                        <tr class="{{ $index % 2 === 0 ? 'row-even' : 'row-odd' }}">
                            <td class="d-label">{{ $answer['label'] ?? $answer['key'] ?? 'Question' }}</td>
                            <td class="d-value">{{ $answerValue }}</td>
                        </tr>
                    @endforeach
                </table>
            </section>
        @endif

        @if ($briefParagraphs !== [])
            <section class="section">
                <div class="section-heading">Brief &amp; cost breakdown</div>
                <div class="text-card">
                    @foreach ($briefParagraphs as $paragraph)
                        <p>{!! $paragraph !!}</p>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="section">
            @if ($briefParagraphs === [])
                <div class="section-heading">Cost breakdown</div>
            @endif
            <div class="items-shell">
                <div class="items-header">
                    <table>
                        <tr>
                            <td style="width:58%;">Description</td>
                            <td style="width:12%; text-align:right;">Qty</td>
                            <td style="width:14%; text-align:right;">Unit price</td>
                            <td style="width:16%; text-align:right;">Total</td>
                        </tr>
                    </table>
                </div>
                <table class="items-table">
                    <colgroup><col style="width:58%;"><col style="width:12%;"><col style="width:14%;"><col style="width:16%;"></colgroup>
                    <tbody>
                        @foreach ($proposal->lineItems as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="numeric">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                                <td class="numeric">£{{ rtrim(rtrim(number_format((float) $item->unit_price, 2, '.', ''), '0'), '.') }}</td>
                                <td class="numeric">£{{ number_format((float) $item->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="total-wrap">
                <div class="total-card">
                    <table class="mini-totals">
                        <tr><td>Subtotal</td><td>£{{ number_format((float) $proposal->subtotal, 2) }}</td></tr>
                    </table>
                    <div class="total-rule"></div>
                    <table class="total-due-table"><tr><td class="total-due-label">Total</td><td class="total-due-amount">£{{ number_format((float) $proposal->total, 2) }}</td></tr></table>
                </div>
                <div class="clear"></div>
            </div>
        </section>

        @if ($termsParagraphs !== [])
            <section class="section">
                <div class="section-heading">Terms &amp; approval</div>
                <div class="section-subtext">Please review the terms below before approving your proposal.</div>
                <div class="text-card">
                    @foreach ($termsParagraphs as $paragraph)
                        <p>{!! $paragraph !!}</p>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="cta-panel">
            <table class="cta-table">
                <tr>
                    <td class="cta-left">
                        <div class="cta-heading">Ready to move forward?</div>
                        <div class="cta-text">Please review this proposal in your customer portal at crm.web-stamp.co.uk, where you can approve or decline it.</div>
                    </td>
                    <td class="cta-right">
                        <a class="cta-button" href="https://crm.web-stamp.co.uk">Review in your portal</a>
                    </td>
                </tr>
            </table>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-rule"></div>
        <table class="footer-table"><tr><td>web-stamp.co.uk</td><td>info@web-stamp.co.uk</td></tr></table>
    </footer>
</div>
</body>
</html>
