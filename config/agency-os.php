<?php

return [
    'admin_email' => env('AGENCY_ADMIN_EMAIL', 'info@web-stamp.co.uk'),
    'opportunity_values' => [
        'hosting' => ['project' => 0, 'monthly' => 35],
        'seo' => ['project' => 250, 'monthly' => 350],
        'care_plan' => ['project' => 0, 'monthly' => 95],
        'website_management' => ['project' => 0, 'monthly' => 175],
        'new_website' => ['project' => 3500, 'monthly' => 0],
        'upsell' => ['project' => 500, 'monthly' => 0],
        'retention' => ['project' => 0, 'monthly' => 100],
    ],
];
