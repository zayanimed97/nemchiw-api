<?php

return [
    'message_max' => 1000,
    'page_size' => 50,
    // A chat screen counts as open this long after its last "viewing" ping (the app pings every 60 s).
    'viewing_seconds' => 90,
    // A joined request's conversation stays writable this many days after the trip ends.
    'joined_open_days' => 7,
    'photo_max_kb' => 8192,
    'messages_per_minute' => 30,
    'photos_per_hour' => 20,
    // A feed cursor older than this gets the full list again.
    'full_after_days' => 90,
    // The feed hands back a cursor this far in the past so late commits are not skipped.
    'cursor_lag_seconds' => 2,
    // Ably's Pusher protocol adapter. Empty key: realtime is off (the app polls).
    'realtime' => [
        'app_id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'host' => env('PUSHER_HOST', 'main.pusher.ably.net'),
    ],
];
