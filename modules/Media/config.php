<?php

return [
    'disk' => env('PHOTOS_DISK', 'photos'),
    // Upload body limit for POST /me/photo (the file itself is capped at 5 MB by validation).
    'max_body_bytes' => 6_291_456,
];
