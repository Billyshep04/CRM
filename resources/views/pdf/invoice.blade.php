<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Invoice {{ $invoice->invoice_number }}</title>
        <style>
            * { box-sizing: border-box; }
            body {
                font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
                color: #111827;
                font-size: 12px;
                line-height: 1.5;
                margin: 0;
                padding: 24px;
            }
            .header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 24px;
            }
            .badge {
                background: #2fb8f0;
                color: #ffffff;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }
            .section {
                margin-bottom: 20px;
            }
            .muted {
                color: #6b7280;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 8px;
            }
            th, td {
                padding: 10px 8px;
                border-bottom: 1px solid #e5e7eb;
                text-align: left;
            }
            th {
                background: #f9fafb;
                font-weight: 600;
            }
            .totals {
                margin-top: 16px;
                width: 100%;
            }
            .totals td {
                padding: 6px 8px;
                border: none;
            }
            .totals .label {
                text-align: right;
                color: #6b7280;
                width: 80%;
            }
            .totals .amount {
                text-align: right;
                font-weight: 600;
                width: 20%;
            }
            .footer {
                margin-top: 24px;
                font-size: 11px;
                color: #6b7280;
            }
        </style>
    </head>
    <body>
        @php
            $hasSubscriptionLine = $invoice->lineItems->contains(function ($item) {
                return $item->billable_type === \App\Models\Subscription::class;
            });
            $hasJobLine = $invoice->lineItems->contains(function ($item) {
                return $item->billable_type === \App\Models\Job::class;
            });

            if ($hasSubscriptionLine && !$hasJobLine) {
                $invoiceTypeLabel = 'subscription';
            } elseif ($hasJobLine && !$hasSubscriptionLine) {
                $invoiceTypeLabel = 'job';
            } elseif ($hasJobLine && $hasSubscriptionLine) {
                $invoiceTypeLabel = 'mixed';
            } else {
                $invoiceTypeLabel = 'invoice';
            }
        @endphp

        <div class="header">
            <div>
                <h1 style="margin: 0 0 6px 0;">Invoice</h1>
                <div class="muted">Invoice #{{ $invoice->invoice_number }}</div>
            </div>
            <div style="text-align: right;">
                <div class="badge">{{ $invoiceTypeLabel }}</div>
                <div class="muted" style="margin-top: 6px;">Issued {{ $invoice->issue_date->format('M j, Y') }}</div>
                <div class="muted">Due {{ $invoice->due_date->format('M j, Y') }}</div>
            </div>
        </div>

        <div class="section">
            <div style="font-weight: 600;">Billed To</div>
            <div>{{ $customer?->name }}</div>
            <div class="muted">{{ $customer?->email }}</div>
            <div class="muted" style="white-space: pre-line;">{{ $customer?->billing_address }}</div>
        </div>

        <div class="section">
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="text-align:right;">Qty</th>
                        <th style="text-align:right;">Unit</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->lineItems as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td style="text-align:right;">{{ $item->quantity }}</td>
                            <td style="text-align:right;">£{{ number_format($item->unit_price, 2) }}</td>
                            <td style="text-align:right;">£{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="totals">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="amount">£{{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Tax</td>
                    <td class="amount">£{{ number_format($invoice->tax_amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Total</td>
                    <td class="amount">£{{ number_format($invoice->total, 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            Thank you for your business. If you have any questions, please contact support.
        </div>
    </body>
</html>
