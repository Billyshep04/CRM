<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>{{ $monthLabel }} SEO Report</title>
    </head>
    <body style="margin:0;padding:0;background:#f3f4f6;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">
            <tr>
                <td align="center">
                    <table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;padding:24px;font-family:Arial, Helvetica, sans-serif;color:#111827;border-top:4px solid #2fb8f0;">
                        <tr>
                            <td style="font-size:20px;font-weight:700;padding-bottom:8px;">Your {{ $monthLabel }} SEO Report</td>
                        </tr>
                        <tr>
                            <td style="font-size:14px;color:#6b7280;padding-bottom:16px;">
                                Hi {{ $greetingName }}, your SEO report for {{ $report->website_domain }} is attached.
                            </td>
                        </tr>
                        @if ($summary !== '')
                            <tr>
                                <td style="padding:12px 16px;background:#f9fafb;border-radius:10px;">
                                    <div style="font-size:12px;color:#6b7280;">This month in brief</div>
                                    <div style="font-size:14px;color:#111827;margin-top:6px;line-height:1.5;">{{ $summary }}</div>
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td style="font-size:13px;color:#6b7280;padding-top:16px;">
                                You can also view and download all of your SEO reports in your portal under Websites.
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size:13px;color:#6b7280;padding-top:10px;">
                                Portal: crm.web-stamp.co.uk
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size:11px;color:#9ca3af;padding-top:20px;">
                                If you have any questions, reply to this email.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
