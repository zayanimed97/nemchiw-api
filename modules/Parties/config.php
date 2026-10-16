<?php

return [
    // Keys the HMAC of placeholder contacts. Never change it once invitations exist.
    'contact_key' => env('PARTIES_CONTACT_KEY', ''),
    // The leader plus up to 19 others, placeholders included.
    'max_size' => 20,
    // A forming party nobody touched for this long is ended by parties:end-idle.
    'idle_days' => 30,
    // One reminder push per invited member in this window.
    'remind_hours' => 12,
    'lookups_per_hour' => 20,
    'lookups_per_day' => 60,
];
