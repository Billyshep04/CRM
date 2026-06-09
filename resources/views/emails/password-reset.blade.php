<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Reset your password</title>
    </head>
    <body style="margin:0;padding:0;background:#f3f4f6;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;padding:24px;font-family:Arial, Helvetica, sans-serif;color:#111827;border-top:4px solid #2fb8f0;">
                        <tr>
                            <td style="font-size:20px;font-weight:700;padding-bottom:8px;">Reset Your Password</td>
                        </tr>
                        <tr>
                            <td style="font-size:14px;color:#6b7280;padding-bottom:16px;">
                                Hi {{ $user->name ?: 'there' }}, use the button below to choose a new password for your WebStamp CRM customer portal.
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0 20px 0;">
                                <a href="{{ $resetUrl }}" style="display:inline-block;background:#2fb8f0;color:#ffffff;text-decoration:none;border-radius:10px;padding:12px 18px;font-size:14px;font-weight:700;">Reset password</a>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size:13px;color:#6b7280;padding-bottom:12px;">
                                This link will expire in {{ config('auth.passwords.users.expire', 60) }} minutes. If you did not request this reset, you can ignore this email.
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size:12px;color:#9ca3af;word-break:break-all;">
                                If the button does not work, paste this link into your browser: {{ $resetUrl }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
