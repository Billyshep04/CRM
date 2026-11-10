<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ match ($state) { 'done' => 'You\'re unsubscribed', 'confirm' => 'Unsubscribe', 'invalid' => 'Link not recognised', default => 'Test email' } }} · {{ $companyName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --accent: {{ $colour }}; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 32px 16px; background: #f7f7f8; color: #111827; font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 15px; line-height: 1.55; -webkit-font-smoothing: antialiased; }
        .panel { width: 100%; max-width: 460px; padding: 36px 32px 30px; background: #ffffff; border: 1px solid rgba(17, 24, 39, 0.1); border-radius: 14px; box-shadow: 0 12px 32px rgba(16, 24, 40, 0.08); text-align: center; }
        .logo { display: block; max-width: 150px; max-height: 56px; margin: 0 auto 26px; }
        .brand { margin: 0 0 26px; font-weight: 700; font-size: 18px; }
        .icon { width: 52px; height: 52px; margin: 0 auto 18px; border-radius: 50%; display: grid; place-items: center; background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--accent); }
        .icon svg { width: 26px; height: 26px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
        h1 { margin: 0 0 10px; font-size: 22px; line-height: 1.25; letter-spacing: -0.01em; }
        p { margin: 0 0 22px; color: #4b5563; }
        .email { color: #111827; font-weight: 600; overflow-wrap: anywhere; }
        button { width: 100%; min-height: 46px; border: 0; border-radius: 10px; background: var(--accent); color: #ffffff; font: inherit; font-weight: 600; cursor: pointer; }
        button:hover { filter: brightness(0.94); }
        button:focus-visible { outline: 3px solid color-mix(in srgb, var(--accent) 35%, transparent); outline-offset: 2px; }
        .note { margin: 18px 0 0; font-size: 13px; color: #6b7280; }
    </style>
</head>
<body>
    <main class="panel">
        <img class="logo" src="{{ url('/api/brand/logo') }}" alt="{{ $companyName }}" onerror="this.hidden = true; this.nextElementSibling.hidden = false;">
        <p class="brand" hidden>{{ $companyName }}</p>

        @if ($state === 'confirm')
            <div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg></div>
            <h1>Unsubscribe from our emails?</h1>
            <p>We'll stop sending marketing emails to <span class="email">{{ $email }}</span>.</p>
            <form method="POST" action="{{ route('email-marketing.unsubscribe', ['token' => $token]) }}">
                <button type="submit">Unsubscribe</button>
            </form>
            <p class="note">Changed your mind? Just close this page.</p>
        @elseif ($state === 'done')
            <div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12 5 5L20 7"/></svg></div>
            <h1>You're unsubscribed</h1>
            <p>@isset($email)<span class="email">{{ $email }}</span> won't receive any more marketing emails from {{ $companyName }}.@else You won't receive any more marketing emails from {{ $companyName }}.@endisset</p>
            <p class="note">You may still get emails about work we're doing for you, such as invoices.</p>
        @elseif ($state === 'test')
            <div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg></div>
            <h1>This was a test email</h1>
            <p>{{ $message }}</p>
        @else
            <div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg></div>
            <h1>We don't recognise that link</h1>
            <p>It may have been copied incompletely. To stop our emails, reply to any of them and ask us to unsubscribe you.</p>
        @endif
    </main>
</body>
</html>
