<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Proposal Approved</title>
    </head>
    <body style="margin:0;padding:0;background:#f3f4f6;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">
            <tr>
                <td align="center">
                    <table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;padding:24px;font-family:Arial, Helvetica, sans-serif;color:#111827;border-top:4px solid #2fb8f0;">
                        <tr>
                            <td style="font-size:20px;font-weight:700;padding-bottom:8px;">Proposal approved</td>
                        </tr>
                        <tr>
                            <td style="font-size:14px;color:#6b7280;padding-bottom:12px;">
                                A customer has approved a proposal in the portal.
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 16px;background:#f9fafb;border-radius:10px;font-size:13px;line-height:1.6;">
                                <div><strong>Proposal:</strong> {{ $proposal->proposal_number }} (v{{ $proposal->version }})</div>
                                <div><strong>Customer:</strong> {{ $customer?->name }} ({{ $customer?->email }})</div>
                                <div><strong>Total:</strong> £{{ number_format((float) $proposal->total, 2) }}</div>
                                @if ($job)
                                    <div><strong>Job:</strong> #{{ $job->id }} - {{ $job->description }}</div>
                                @endif
                                <div><strong>Approved at:</strong> {{ $proposal->accepted_at?->format('M j, Y H:i') }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size:13px;color:#6b7280;padding-top:16px;">
                                Review in CRM: crm.web-stamp.co.uk
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
