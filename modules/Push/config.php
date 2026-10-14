<?php

return [
    'expo_url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
    // Only when "enhanced push security" is turned on in the EAS dashboard.
    'access_token' => env('EXPO_ACCESS_TOKEN'),
    'batch' => 100,
    // Times in push texts are shown in Tunisia's time.
    'timezone' => 'Africa/Tunis',
];
