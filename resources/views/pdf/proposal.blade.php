<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Proposal {{ $proposal->proposal_number }} v{{ $proposal->version }}</title>
        <style>
            * { box-sizing: border-box; }
            body {
                font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
                color: #0f172a;
                font-size: 12px;
                line-height: 1.5;
                margin: 0;
                padding: 20px;
                background: #f8fbff;
            }
            .sheet {
                background: #ffffff;
                border: 1px solid #dce6f2;
                border-radius: 16px;
                overflow: hidden;
            }
            .hero {
                background: linear-gradient(135deg, #2fb8f0 0%, #0f172a 75%);
                color: #ffffff;
                padding: 18px 20px;
            }
            .hero-grid {
                width: 100%;
            }
            .logo-wrap {
                text-align: right;
            }
            .logo {
                display: inline-block;
                width: 52px;
                height: 52px;
                object-fit: contain;
                background: rgba(255, 255, 255, 0.12);
                border: 1px solid rgba(255, 255, 255, 0.28);
                border-radius: 12px;
                padding: 6px;
            }
            .hero h1 {
                margin: 0;
                font-size: 24px;
                letter-spacing: 0.01em;
            }
            .hero-meta {
                margin-top: 6px;
                font-size: 12px;
                opacity: 0.95;
            }
            .status-pill {
                display: inline-block;
                margin-top: 8px;
                padding: 4px 10px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.18);
                border: 1px solid rgba(255, 255, 255, 0.28);
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }
            .content {
                padding: 18px 20px 20px;
            }
            .proposal-title {
                margin: 14px 0 0;
                color: #31B8F0;
                font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
                font-size: 24px;
                line-height: 1.2;
                font-weight: 700;
                letter-spacing: 0;
            }
            .proposal-for {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 16px;
            }
            .proposal-for td {
                vertical-align: top;
                width: 100%;
                padding-right: 0;
            }
            .label {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: #64748b;
                margin-bottom: 4px;
            }
            .box {
                background: #f8fbff;
                border: 1px solid #dce6f2;
                border-radius: 12px;
                padding: 10px 12px;
                margin-top: 4px;
            }
            .box p {
                margin: 0;
                white-space: pre-line;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                padding: 9px 8px;
                border-bottom: 1px solid #e5e7eb;
            }
            th {
                text-align: left;
                background: #f8fbff;
                color: #334155;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .num {
                text-align: right;
            }
            .totals {
                margin-top: 12px;
                margin-left: auto;
                width: 260px;
                border-collapse: collapse;
            }
            .totals td {
                border: none;
                padding: 6px 8px;
            }
            .totals .name {
                color: #64748b;
                text-align: right;
            }
            .totals .value {
                text-align: right;
                font-weight: 700;
            }
            .terms {
                margin-top: 14px;
                border-top: 1px dashed #dce6f2;
                padding-top: 12px;
            }
            .answers {
                margin-top: 14px;
            }
            .answer-grid {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                overflow: hidden;
            }
            .answer-grid td {
                border-bottom: 1px solid #e5e7eb;
                padding: 8px 10px;
            }
            .answer-grid tr:last-child td {
                border-bottom: none;
            }
            .answer-label {
                width: 45%;
                color: #475569;
                font-weight: 700;
                background: #f8fbff;
            }
            .footer {
                margin-top: 14px;
                color: #64748b;
                font-size: 11px;
            }
        </style>
    </head>
    <body>
        @php
            $logoDataUri = null;
            $logoPath = public_path('favicon.png');
            if (is_file($logoPath)) {
                $logoContents = file_get_contents($logoPath);
                if ($logoContents !== false) {
                    $logoMime = mime_content_type($logoPath) ?: 'image/png';
                    $logoDataUri = 'data:' . $logoMime . ';base64,' . base64_encode($logoContents);
                }
            }

            $statusLabel = $proposal->effectiveStatus();
        @endphp

        <div class="sheet">
            <div class="hero">
                <table class="hero-grid">
                    <tr>
                        <td>
                            <h1>Proposal</h1>
                            <div class="hero-meta">#{{ $proposal->proposal_number }} &middot; Version {{ $proposal->version }}</div>
                            <div class="hero-meta">Issued {{ $proposal->issue_date?->format('M j, Y') }} &middot; Valid until {{ $proposal->expiry_date?->format('M j, Y') }}</div>
                            <div class="status-pill">{{ $statusLabel }}</div>
                            <h2 class="proposal-title">{{ $proposal->title }}</h2>
                        </td>
                        <td class="logo-wrap">
                            @if ($logoDataUri)
                                <img class="logo" src="{{ $logoDataUri }}" alt="WebStamp logo">
                            @endif
                        </td>
                    </tr>
                </table>
            </div>

            <div class="content">
                <table class="proposal-for">
                    <tr>
                        <td>
                            <div class="label">Proposal For</div>
                            <div class="box">
                                <p><strong>{{ $customer?->name }}</strong></p>
                                <p>{{ $customer?->email }}</p>
                                <p>{{ $customer?->billing_address }}</p>
                            </div>
                        </td>
                    </tr>
                </table>

                @if (is_array($proposal->form_answers) && count($proposal->form_answers))
                    <div class="answers">
                        <div class="label">Proposal details</div>
                        <table class="answer-grid">
                            <tbody>
                                @foreach ($proposal->form_answers as $answer)
                                    @php
                                        $answerValue = $answer['value'] ?? null;
                                        if (is_bool($answerValue)) {
                                            $answerValue = $answerValue ? 'Yes' : 'No';
                                        }
                                        if ($answerValue === null || $answerValue === '') {
                                            $answerValue = 'Not specified';
                                        }
                                    @endphp
                                    <tr>
                                        <td class="answer-label">{{ $answer['label'] ?? $answer['key'] ?? 'Question' }}</td>
                                        <td>{{ $answerValue }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (trim((string) $proposal->notes) !== '')
                    <div class="label">Notes</div>
                    <div class="box">
                        <p>{{ $proposal->notes }}</p>
                    </div>
                @endif

                <table style="margin-top: 12px;">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="num">Qty</th>
                            <th class="num">Unit</th>
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proposal->lineItems as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                                <td class="num">£{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="num">£{{ number_format((float) $item->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <table class="totals">
                    <tr>
                        <td class="name">Subtotal</td>
                        <td class="value">£{{ number_format((float) $proposal->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="name">Total</td>
                        <td class="value">£{{ number_format((float) $proposal->total, 2) }}</td>
                    </tr>
                </table>

                @if (trim((string) $proposal->terms) !== '')
                    <div class="terms">
                        <div class="label">Terms</div>
                        <div class="box">
                            <p>{{ $proposal->terms }}</p>
                        </div>
                    </div>
                @endif

                <div class="footer">
                    Please review this proposal in your customer portal at crm.web-stamp.co.uk, where you can approve or decline it.
                </div>
            </div>
        </div>
    </body>
</html>
