<?php

return [
    // Private disk for identity, licence and insurance documents. Never the public media disk.
    'documents_disk' => env('ORGANISER_DOCUMENTS_DISK', 'local'),
    // Upload body limit for one document (the file itself is capped at 10 MB by validation).
    'max_body_bytes' => 11_534_336,
    // Days documents are kept after a refusal or revocation.
    'purge_after_days' => 30,
];
