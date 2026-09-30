<?php

return [
    'code_ttl' => 300,
    'resend_after' => 60,
    'max_attempts' => 5,
    // Codes per phone that stay usable at once (a resend keeps the older ones).
    'live_codes' => 3,
    'limits' => [
        'per_phone_per_hour' => 5,
        'per_ip_per_hour' => 60, // high on purpose: carrier NAT puts many people on one IP
        // Caps SMS spend if someone pumps codes to many numbers.
        'global_per_hour' => (int) env('OTP_GLOBAL_PER_HOUR', 300),
        'verify_per_ip_per_10_min' => 60,
        'per_user_attach_per_hour' => 5,
        // Wrong codes per phone (all challenges) before it is locked for 24 h.
        'failures_per_phone_per_day' => 10,
    ],
    'sms_driver' => env('SMS_DRIVER', 'log'),
];
