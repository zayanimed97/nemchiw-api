<?php

return [
    // Keys the HMAC of placeholder contacts. Never change it once invitations exist.
    'contact_key' => env('PARTIES_CONTACT_KEY', ''),
    // The leader plus up to 19 others, placeholders included.
    'max_size' => 20,
    'lookups_per_hour' => 20,
    'lookups_per_day' => 60,
];
