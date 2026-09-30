<?php

return [
    'code_ttl' => 300,
    'resend_after' => 60,
    'max_attempts' => 5,
    'limits' => [
        'per_phone_per_hour' => 5,
        'per_ip_per_hour' => 20,
        // Caps SMS spend if someone pumps codes to many numbers.
        'global_per_hour' => (int) env('OTP_GLOBAL_PER_HOUR', 300),
        'verify_per_ip_per_10_min' => 30,
    ],
    'sms_driver' => env('SMS_DRIVER', 'log'),
];
