<?php

return [
    // Requests per minute per signed-in user, or per IP when signed out (carrier NAT).
    'api_per_minute' => 300,

    // Deploy check only: GET /api/v1/_client-ip echoes the IP the rate limiters see.
    // Turn on, compare with your real IP and a forged X-Forwarded-For, turn off.
    'expose_client_ip' => (bool) env('EXPOSE_CLIENT_IP', false),
];
