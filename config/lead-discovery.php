<?php

return [
    'provider' => env('LEAD_DISCOVERY_PROVIDER', 'google_places'),
    'queue' => env('LEAD_DISCOVERY_QUEUE', 'discovery'),
    'run_synchronously' => env('LEAD_DISCOVERY_RUN_SYNCHRONOUSLY', false),
    'auto_audit' => env('LEAD_DISCOVERY_AUTO_AUDIT', true),
    'google_places' => [
        'api_key' => env('GOOGLE_PLACES_API_KEY'),
        'base_url' => env('GOOGLE_PLACES_BASE_URL', 'https://places.googleapis.com/v1'),
        'timeout' => (int) env('GOOGLE_PLACES_TIMEOUT', 20),
    ],
];
