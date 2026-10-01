<?php

$list = fn (string $value) => array_values(array_filter(array_map('trim', explode(',', $value))));

return [
    // Audiences we accept. Google puts the app's *web* client id in `aud`.
    'google' => ['client_ids' => $list((string) env('GOOGLE_CLIENT_IDS', ''))],
    'apple' => ['client_ids' => $list((string) env('APPLE_CLIENT_IDS', 'tn.nemchiw.app'))],
    'facebook' => [
        'app_id' => (string) env('FACEBOOK_APP_ID', ''),
        'app_secret' => (string) env('FACEBOOK_APP_SECRET', ''),
        'graph_version' => (string) env('FACEBOOK_GRAPH_VERSION', 'v23.0'),
    ],
    'jwks_ttl' => 21_600,
    'jwks_refresh_cooldown' => 60,
    'leeway' => 60,
    'per_ip_per_10_min' => 60,
];
