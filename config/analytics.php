<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Analytics driver
    |--------------------------------------------------------------------------
    |
    | "google" talks to the GA4 Data + Admin APIs using a service account.
    | "mock" returns deterministic sample data and never leaves the process,
    | which is the default so nothing calls Google until it is configured.
    |
    */

    'driver' => env('ANALYTICS_DRIVER', 'mock'),

    'queue' => env('ANALYTICS_QUEUE', 'default'),

    /*
    | How far back a first-time backfill reaches, and the trailing window that
    | every scheduled run re-pulls to catch late-arriving GA4 data.
    */
    'backfill_days' => (int) env('GOOGLE_ANALYTICS_BACKFILL_DAYS', 365),
    'refetch_window_days' => (int) env('GOOGLE_ANALYTICS_REFETCH_DAYS', 3),

    'google' => [
        // Absolute path to the service-account JSON key, kept outside the deploy
        // path. Alternatively set GOOGLE_ANALYTICS_CREDENTIALS_JSON to the raw or
        // base64-encoded JSON (handy on cPanel).
        'credentials_path' => env('GOOGLE_ANALYTICS_CREDENTIALS_PATH'),
        'credentials_json' => env('GOOGLE_ANALYTICS_CREDENTIALS_JSON'),

        'data_api_base' => env('GOOGLE_ANALYTICS_DATA_API_BASE', 'https://analyticsdata.googleapis.com/v1beta'),
        'admin_api_base' => env('GOOGLE_ANALYTICS_ADMIN_API_BASE', 'https://analyticsadmin.googleapis.com/v1beta'),
        'token_endpoint' => env('GOOGLE_ANALYTICS_TOKEN_ENDPOINT', 'https://oauth2.googleapis.com/token'),
        'scope' => env('GOOGLE_ANALYTICS_SCOPE', 'https://www.googleapis.com/auth/analytics.readonly'),

        'timeout' => (int) env('GOOGLE_ANALYTICS_TIMEOUT', 30),
    ],
];
