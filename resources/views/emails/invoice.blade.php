<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Invoice {{ $invoice->invoice_number }}</title>
    </head>
    <body style="margin:0;padding:0;background:#f3f4f6;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;padding:24px;font-family:Arial, Helvetica, sans-serif;color:#111827;border-top:4px solid #2fb8f0;">
                        <tr>
                            <td style="font-size:20px;font-weight:700;padding-bottom:8px;">Your Invoice Is Ready</td>
                        </tr>
                        <tr>
                            <td style="font-size:14px;color:#6b7280;padding-bottom:16px;">
                                Hi {{ $customer?->name ?? 'there' }}, your invoice {{ $invoice->invoice_number }} is attached.
                            </td>
                        </tr>
                        @if ($invoice->payment_link)
                            <tr>
                                <td style="padding:16px 18px 18px;background:#eff8ff;border:1px solid #bae6fd;border-radius:10px;">
                                    <div style="font-size:16px;font-weight:700;color:#111827;">Pay this invoice online</div>
                                    <div style="font-size:13px;color:#4b5563;margin-top:6px;">You can pay this invoice securely using the link below.</div>
                                    <div style="margin-top:14px;">
                                        <a href="{{ $invoice->payment_link }}" style="display:inline-block;padding:11px 18px;border-radius:8px;background:#2fb8f0;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">Pay invoice</a>
                                    </div>
                                    <div style="font-size:11px;color:#6b7280;margin-top:12px;line-height:1.5;">
                                        If the button does not work, copy and paste this link into your browser:<br>
                                        <a href="{{ $invoice->payment_link }}" style="color:#0369a1;word-break:break-all;">{{ $invoice->payment_link }}</a>
                                    </div>
                                </td>
                            </tr>
                            <tr><td style="height:16px;"></td></tr>
                        @endif
                        <tr>
                            <td style="padding:12px 16px;background:#f9fafb;border-radius:10px;">
                                <div style="font-size:12px;color:#6b7280;">Amount Due</div>
                                <div style="font-size:22px;font-weight:700;">£{{ number_format($invoice->total, 2) }}</div>
                                <div style="font-size:12px;color:#6b7280;margin-top:4px;">Due {{ $invoice->due_date->format('M j, Y') }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size:13px;color:#6b7280;padding-top:16px;">
                                If you have questions, reply to this email and our team will help.
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size:13px;color:#6b7280;padding-top:10px;">
                                To login and see all your invoices and mark as 'paid' please login to your portal here: crm.web-stamp.co.uk
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size:11px;color:#9ca3af;padding-top:20px;">
                                Please do not share this invoice with anyone who is not authorized.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
