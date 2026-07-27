<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <div style="max-width:620px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;border-top:4px solid #2fb8f0;">
        <h1 style="font-size:22px;margin:0 0 12px;">Customer form completed</h1>
        <p><strong>{{ $customer?->name ?: 'Customer' }}</strong> has submitted <strong>{{ $formRequest->template_name }}</strong>.</p>
        @if ($customer?->email)<p>Customer email: <a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a></p>@endif
        <p>Completed: {{ $formRequest->completed_at?->format('j M Y, H:i') }}</p>
        <p style="margin:24px 0;"><a href="{{ $crmUrl }}" style="display:inline-block;background:#2fb8f0;color:#071821;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:9px;">Review in CRM</a></p>
    </div>
</body>
</html>
