<?php

return [
    'didit' => [
        'base_url' => rtrim((string) env('DIDIT_BASE_URL', 'https://verification.didit.me'), '/'),
        'api_key' => (string) env('DIDIT_API_KEY', ''),
        'workflow_id' => (string) env('DIDIT_WORKFLOW_ID', ''),
        'webhook_secret' => (string) env('DIDIT_WEBHOOK_SECRET', ''),
    ],
    // Each check costs money: at most this many sessions per person per 24 h.
    'sessions_per_day' => 5,
    // Circuit breaker for the whole service (cost cap if accounts are mass-created).
    'global_sessions_per_day' => (int) env('VERIFICATION_GLOBAL_PER_DAY', 300),
    // Didit signs webhooks with a timestamp; older (or future) ones are refused.
    'webhook_tolerance_seconds' => 300,
];
