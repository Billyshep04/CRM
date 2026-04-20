<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Proposal {{ $proposal->proposal_number }}</title>
    </head>
    <body style="margin:0;padding:0;background:#f3f4f6;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">
            <tr>
                <td align="center">
                    <table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;padding:24px;font-family:Arial, Helvetica, sans-serif;color:#111827;border-top:4px solid #2fb8f0;">
                        <tr>
                            <td style="font-size:20px;font-weight:700;padding-bottom:8px;">Your Proposal Is Ready</td>
                        </tr>
                        <tr>
                            <td style="font-size:14px;color:#6b7280;padding-bottom:16px;">
                                Hi {{ $customer?->name ?? 'there' }}, your proposal {{ $proposal->proposal_number }} (v{{ $proposal->version }}) is attached.
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 16px;background:#f9fafb;border-radius:10px;">
                                <div style="font-size:12px;color:#6b7280;">Proposal total</div>
                                <div style="font-size:22px;font-weight:700;">£{{ number_format((float) $proposal->total, 2) }}</div>
                                <div style="font-size:12px;color:#6b7280;margin-top:4px;">Valid until {{ $proposal->expiry_date?->format('M j, Y') }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size:13px;color:#6b7280;padding-top:16px;">
                                Please review and accept/reject this proposal in your portal.
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
