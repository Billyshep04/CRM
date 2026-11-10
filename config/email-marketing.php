<?php

$defaultFrom = env('MARKETING_FROM_ADDRESS', env('MAIL_FROM_ADDRESS'));

return [

    // The mailer in config/mail.php used for campaigns and test sends.
    'mailer' => env('MARKETING_MAILER', 'marketing'),

    // Pre-filled on new campaigns.
    'from_address' => $defaultFrom,
    'from_name' => env('MARKETING_FROM_NAME', env('MAIL_FROM_NAME')),
    'reply_to' => env('MARKETING_REPLY_TO'),

    // Campaigns may only send from these domains, which must have SPF, DKIM and DMARC set up.
    'allowed_from_domains' => array_values(array_filter(array_map(
        fn ($domain) => strtolower(trim($domain)),
        explode(',', (string) env('MARKETING_ALLOWED_FROM_DOMAINS', $defaultFrom ? substr(strrchr($defaultFrom, '@'), 1) : '')),
    ))),

    'max_test_recipients' => 5,

    // Campaign emails sent per minute. Keep within your SMTP2GO plan's limits; a steady
    // pace also looks better to spam filters than a burst.
    'rate_per_minute' => max(1, (int) env('MARKETING_RATE_PER_MINUTE', 60)),

];
