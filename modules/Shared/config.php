<?php

return [
    // Requests per minute per signed-in user, or per IP when signed out (carrier NAT).
    'api_per_minute' => 300,

    // Deploy check only: GET /api/v1/_client-ip echoes the IP the rate limiters see.
    // Turn on, compare with your real IP and a forged X-Forwarded-For, turn off.
    'expose_client_ip' => (bool) env('EXPOSE_CLIENT_IP', false),

    'places' => [
        // Rows per incremental page. When a page is full, serverTime is the cursor for the next one.
        'page_size' => 1000,
        // How far behind "now" a partial page's serverTime is, so late commits are not skipped.
        'cursor_lag_seconds' => 120,
        // Soft-deleted places are kept this long so `removed` can report them; older cursors get a full set.
        'tombstone_days' => 90,
    ],
];
