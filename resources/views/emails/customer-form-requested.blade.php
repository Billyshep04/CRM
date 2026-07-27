<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <div style="max-width:620px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;border-top:4px solid #2fb8f0;">
        <h1 style="font-size:22px;margin:0 0 12px;">A new form is waiting</h1>
        <p>Hi {{ $customer?->name ?: 'there' }},</p>
        <p>WebStamp has sent you the <strong>{{ $formRequest->template_name }}</strong> form to complete.</p>
        <p style="margin:24px 0;"><a href="{{ $portalUrl }}" style="display:inline-block;background:#2fb8f0;color:#071821;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:9px;">Open customer portal</a></p>
        <p style="font-size:13px;color:#6b7280;">Sign in and select Forms to complete it.</p>
    </div>
</body>
</html>
