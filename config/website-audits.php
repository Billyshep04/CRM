<?php

return [
    'queue' => env('WEBSITE_AUDIT_QUEUE', 'audit'),
    'timeout_seconds' => (int) env('WEBSITE_AUDIT_TIMEOUT', 15),
    'connect_timeout_seconds' => (int) env('WEBSITE_AUDIT_CONNECT_TIMEOUT', 5),
    'max_redirects' => (int) env('WEBSITE_AUDIT_MAX_REDIRECTS', 8),
    'max_body_bytes' => (int) env('WEBSITE_AUDIT_MAX_BODY_BYTES', 5_000_000),
    'max_links_to_check' => (int) env('WEBSITE_AUDIT_MAX_LINKS', 50),
    'user_agent' => env('WEBSITE_AUDIT_USER_AGENT', 'LeadForge Website Auditor/1.0'),
    'enforce_public_networks' => (bool) env('WEBSITE_AUDIT_ENFORCE_PUBLIC_NETWORKS', true),
];
