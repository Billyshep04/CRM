<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; width: 210mm; min-height: 297mm; }
        body {
            color: #0b1d24;
            background: #ffffff;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 9pt;
            line-height: 1.35;
        }
        .page { width: 210mm; min-height: 297mm; background: #ffffff; }
        .top-rule { height: 4.7mm; background: #30b8f0; }
        .hero {
            height: 36.1mm;
            padding: 6.2mm 12.6mm 0;
            background: #0d1c23;
            color: #ffffff;
        }
        .hero-table, .pair-table, .details-grid, .items-table, .summary-table, .payment-table, .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .hero-left { width: 55%; vertical-align: top; }
        .hero-right { width: 45%; vertical-align: top; text-align: right; }
        .brand-crop {
            position: relative;
            width: 43.5mm;
            height: 24.2mm;
            margin-top: 5mm;
        }
        .brand-crop .brand-image {
            display: block;
            width: 43.5mm;
            height: 24.2mm;
        }
        .brand-crop .brand-stamp {
            position: absolute;
            width: 10.65mm;
            height: 9mm;
            left: 31.3mm;
            top: -2.4mm;
            transform: rotate(8.3deg);
            transform-origin: top left;
        }
        .brand-fallback { width: 46mm; line-height: .72; font-weight: 800; letter-spacing: -.9mm; }
        .brand-fallback .web { display: inline-block; color: #30b8f0; font-size: 25pt; }
        .brand-fallback .fallback-mark {
            display: inline-block;
            width: 9mm;
            height: 9mm;
            margin-left: 2mm;
            border: .8mm solid #ffffff;
            border-radius: 1.2mm;
            vertical-align: top;
            transform: rotate(7deg);
        }
        .brand-fallback .stamp { display: block; color: #ffffff; font-size: 25pt; }
        .hero-right { position: relative; top: -2.8mm; }
        .invoice-title { font-size: 19pt; line-height: 1; font-weight: 800; letter-spacing: .15mm; }
        .invoice-number { margin-top: 1.5mm; color: #aebdc5; font-size: 11.5pt; font-weight: 700; }
        .due-pill {
            display: inline-block;
            margin-top: 1.5mm;
            min-width: 49mm;
            padding: 2.2mm 3mm 2mm;
            border-radius: 7mm;
            background: #30b8f0;
            color: #0b1d24;
            text-align: center;
            font-size: 8pt;
            font-weight: 800;
        }
        .due-pill .date { margin-left: 3.2mm; color: #ffffff; }
        .content { padding: 12.5mm 12.6mm 0; }
        .pair-table td { vertical-align: top; }
        .pair-left, .pair-right {
            width: 46.5%;
            padding: 4.8mm 5.8mm 5.6mm;
            border-radius: 2.8mm;
        }
        .pair-left { background: #f0f6f8; }
        .pair-gap { width: 7%; }
        .pair-right { background: #e4f5fc; }
        .eyebrow {
            color: #71838d;
            font-size: 5.5pt;
            font-weight: 800;
            text-transform: uppercase;
        }
        .customer-name { margin-top: 1.5mm; font-size: 12.5pt; line-height: 1.05; font-weight: 800; }
        .customer-contact { margin-top: 1.5mm; color: #465d68; font-size: 6.3pt; }
        .customer-address { margin-top: .9mm; color: #465d68; font-size: 6.2pt; line-height: 1.15; white-space: pre-line; }
        .details-grid { margin-top: 3.5mm; }
        .details-grid td { width: 33.333%; padding-right: 2mm; }
        .details-label { color: #71838d; font-size: 5pt; font-weight: 800; text-transform: uppercase; }
        .details-value { margin-top: 1.6mm; color: #0b1d24; font-size: 7.5pt; font-weight: 800; }
        .project-note { min-height: 26.2mm; padding-top: 6.5mm; }
        .project-note-title { color: #71838d; font-size: 6pt; font-weight: 800; text-transform: uppercase; }
        .project-note-text { margin-top: 3.3mm; color: #314952; font-size: 6.5pt; font-weight: 700; white-space: pre-line; }
        .items-shell { width: 100%; }
        .items-header {
            overflow: hidden;
            border-radius: 2.7mm;
            background: #0d1c23;
            color: #ffffff;
        }
        .items-header table { width: 100%; border-collapse: collapse; }
        .items-header td { height: 13.6mm; padding: 0 5.8mm; vertical-align: middle; font-size: 6.3pt; font-weight: 800; text-transform: uppercase; white-space: nowrap; }
        .items-table { table-layout: fixed; }
        .col-description { width: 68%; }
        .col-qty { width: 10%; }
        .col-unit { width: 12%; }
        .col-amount { width: 10%; }
        .items-table td {
            min-height: 5.5mm;
            padding: 4.5mm 5.8mm 3.2mm;
            border-bottom: .5mm solid #dce7eb;
            vertical-align: top;
        }
        .items-table td.numeric { padding-left: 1mm; padding-right: 1mm; text-align: center; vertical-align: middle; }
        .item-title { font-size: 9pt; font-weight: 800; }
        .item-subtitle { margin-top: 2.5mm; color: #82949e; font-size: 7pt; }
        .summary-wrap { margin-top: 20mm; page-break-inside: avoid; }
        .summary-table td { vertical-align: top; }
        .summary-customer-cell { width: 55.3%; }
        .summary-customer {
            padding: 5.3mm 7mm 1.8mm;
            border-radius: 2.7mm;
            background: #f0f6f8;
        }
        .summary-gap { width: 7.2%; }
        .summary-totals-cell { width: 37.5%; }
        .summary-totals {
            padding: 6mm 5.7mm .7mm;
            border-radius: 2.7mm;
            background: #30b8f0;
            color: #06171e;
        }
        .summary-customer .customer-name { margin-top: 3.5mm; }
        .mini-totals { width: 100%; border-collapse: collapse; }
        .mini-totals td { padding: 0 0 2mm; font-size: 7.5pt; }
        .mini-totals td:last-child { text-align: right; font-weight: 800; }
        .total-rule { height: .45mm; margin: .1mm 0 3.3mm; background: rgba(8, 68, 92, .22); }
        .total-due-table { width: 100%; border-collapse: collapse; }
        .total-due-label { font-size: 7.5pt; font-weight: 800; text-transform: uppercase; vertical-align: bottom; }
        .total-due-amount { text-align: right; font-size: 16pt; line-height: 1; font-weight: 800; }
        .payment-panel {
            margin-top: 6.1mm;
            min-height: 24.4mm;
            padding: 7.5mm 7.6mm 1mm;
            border-radius: 2.8mm;
            background: #0d1c23;
            color: #ffffff;
            page-break-inside: avoid;
        }
        .payment-content { position: relative; top: -1.5mm; }
        .payment-label { color: #30b8f0; font-size: 7pt; font-weight: 800; text-transform: uppercase; }
        .payment-table { margin-top: 1.8mm; }
        .payment-left { width: 58%; padding-right: 8mm; border-right: .25mm solid #102c36; vertical-align: top; }
        .payment-right { width: 42%; padding-left: 9mm; vertical-align: top; color: #b7c5cb; font-size: 8.3pt; line-height: 1.75; }
        .account-name { font-size: 10.2pt; }
        .bank-grid { width: 100%; margin-top: 3mm; border-collapse: collapse; }
        .bank-grid td { width: 50%; vertical-align: top; }
        .bank-title { color: #71838d; font-size: 6.8pt; font-weight: 800; text-transform: uppercase; }
        .bank-value { margin-top: 2.1mm; font-size: 10.3pt; }
        .payment-ref { color: #30b8f0; font-size: 9.3pt; font-weight: 800; }
        .footer { margin-top: 9.2mm; padding-bottom: 8mm; page-break-inside: avoid; }
        .footer-rule { height: .5mm; background: #dce7eb; }
        .footer-table { margin-top: 4.8mm; }
        .footer-table td { color: #71838d; font-size: 7.2pt; }
        .footer-table td:first-child { font-weight: 800; text-transform: uppercase; }
        .footer-table td:last-child { text-align: right; }
        a { color: inherit; text-decoration: none; }
        .brand-fallback, .invoice-title, .invoice-number, .due-pill, .eyebrow,
        .customer-name, .details-label, .details-value, .project-note-title,
        .project-note-text, .items-header td, .item-title, .mini-totals td:last-child,
        .total-due-label, .total-due-amount, .payment-label, .bank-title,
        .payment-ref, .footer-table td:first-child {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-weight: bold;
        }
    </style>
</head>
<body>
@php
    $paymentAccountName = trim((string) ($payment_details['account_name'] ?? 'Billy Sheppard'));
    $paymentSortCode = trim((string) ($payment_details['sort_code'] ?? '04-00-03'));
    $paymentAccountNumber = trim((string) ($payment_details['account_number'] ?? '05574495'));
    $paymentDisplayName = str_contains(strtolower($paymentAccountName), 'web stamp')
        ? $paymentAccountName
        : $paymentAccountName . ' trading as Web Stamp';
    $termsDays = max(0, $invoice->issue_date->diffInDays($invoice->due_date));
    $jobNotes = $invoice->lineItems
        ->filter(fn ($item) => $item->billable_type === \App\Models\Job::class && $item->billable instanceof \App\Models\Job)
        ->map(fn ($item) => trim((string) ($item->billable->notes ?? '')))
        ->filter()
        ->unique()
        ->values();
    $projectNote = $jobNotes->implode("\n");
@endphp

<div class="page">
    <div class="top-rule"></div>
    <div class="hero">
        <table class="hero-table">
            <tr>
                <td class="hero-left">
                    @if (!empty($invoice_logo_data_uri))
                        <div class="brand-crop">
                            <img class="brand-image" src="{{ $invoice_logo_data_uri }}" alt="Web Stamp">
                            @if (!empty($invoice_stamp_data_uri))
                                <img class="brand-stamp" src="{{ $invoice_stamp_data_uri }}" alt="">
                            @endif
                        </div>
                    @else
                        <div class="brand-fallback"><span class="web">WEB</span><span class="fallback-mark">&nbsp;</span><span class="stamp">STAMP</span></div>
                    @endif
                </td>
                <td class="hero-right">
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                    <div class="due-pill">PAYMENT DUE:<span class="date">{{ strtoupper($invoice->due_date->format('d M Y')) }}</span></div>
                </td>
            </tr>
        </table>
    </div>

    <main class="content">
        <table class="pair-table">
            <tr>
                <td class="pair-left">
                    <div class="eyebrow">Billed to</div>
                    <div class="customer-name">{{ $customer?->name }}</div>
                    <div class="customer-contact">{{ $customer?->email }}</div>
                    <div class="customer-address">{{ $customer?->billing_address }}</div>
                </td>
                <td class="pair-gap"></td>
                <td class="pair-right">
                    <div class="eyebrow">Invoice details</div>
                    <table class="details-grid">
                        <tr>
                            <td><div class="details-label">Issued</div><div class="details-value">{{ $invoice->issue_date->format('d M Y') }}</div></td>
                            <td><div class="details-label">Due</div><div class="details-value">{{ $invoice->due_date->format('d M Y') }}</div></td>
                            <td><div class="details-label">Terms</div><div class="details-value">{{ $termsDays }} {{ $termsDays === 1 ? 'day' : 'days' }}</div></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <section class="project-note">
            <div class="project-note-title">Project note</div>
            <div class="project-note-text">{{ $projectNote !== '' ? $projectNote : ' ' }}</div>
        </section>

        <section class="items-shell">
            <div class="items-header">
                <table>
                    <tr>
                        <td class="col-description">Description</td>
                        <td class="col-qty" style="text-align:center;">Qty</td>
                        <td class="col-unit" style="text-align:center;">Unit price</td>
                        <td class="col-amount" style="text-align:center;">Amount</td>
                    </tr>
                </table>
            </div>
            <table class="items-table">
                <colgroup><col class="col-description"><col class="col-qty"><col class="col-unit"><col class="col-amount"></colgroup>
                <tbody>
                @foreach ($invoice->lineItems as $item)
                    @php
                        $itemSubtitle = match ($item->billable_type) {
                            \App\Models\Subscription::class => 'Subscription',
                            \App\Models\Job::class => 'Job',
                            default => 'Service',
                        };
                    @endphp
                    <tr>
                        <td style="width:68%;"><div class="item-title">{{ $item->description }}</div><div class="item-subtitle">{{ $itemSubtitle }}</div></td>
                        <td class="numeric" style="width:10%;">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                        <td class="numeric" style="width:12%;">£{{ rtrim(rtrim(number_format((float) $item->unit_price, 2, '.', ''), '0'), '.') }}</td>
                        <td class="numeric" style="width:10%;">£{{ rtrim(rtrim(number_format((float) $item->total, 2, '.', ''), '0'), '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        <section class="summary-wrap">
            <table class="summary-table">
                <tr>
                    <td class="summary-customer-cell">
                        <div class="summary-customer">
                            <div class="eyebrow">Billed to</div>
                            <div class="customer-name">{{ $customer?->name }}</div>
                            <div class="customer-contact">{{ $customer?->email }}</div>
                            <div class="customer-address">{{ $customer?->billing_address }}</div>
                        </div>
                    </td>
                    <td class="summary-gap"></td>
                    <td class="summary-totals-cell">
                        <div class="summary-totals">
                            <table class="mini-totals">
                                <tr><td>Subtotal</td><td>£{{ number_format($invoice->subtotal, 2) }}</td></tr>
                                <tr><td>Tax</td><td>£{{ number_format($invoice->tax_amount, 2) }}</td></tr>
                            </table>
                            <div class="total-rule"></div>
                            <table class="total-due-table"><tr><td class="total-due-label">Total due</td><td class="total-due-amount">£{{ number_format($invoice->total, 2) }}</td></tr></table>
                        </div>
                    </td>
                </tr>
            </table>
        </section>

        <section class="payment-panel">
            <div class="payment-content">
                <div class="payment-label">Payment details</div>
                <table class="payment-table">
                    <tr>
                        <td class="payment-left">
                            <div class="account-name">{{ $paymentDisplayName }}</div>
                            <table class="bank-grid"><tr>
                                <td><div class="bank-title">Account number</div><div class="bank-value">{{ $paymentAccountNumber }}</div></td>
                                <td><div class="bank-title">Sort code</div><div class="bank-value">{{ $paymentSortCode }}</div></td>
                            </tr></table>
                        </td>
                        <td class="payment-right">Please use the invoice number<br>as your payment reference.<br><span class="payment-ref">REF&nbsp; {{ $invoice->invoice_number }}</span></td>
                    </tr>
                </table>
            </div>
        </section>

        <footer class="footer">
            <div class="footer-rule"></div>
            <table class="footer-table"><tr><td>web-stamp.co.uk</td><td>info@web-stamp.co.uk</td></tr></table>
        </footer>
    </main>
</div>
</body>
</html>
