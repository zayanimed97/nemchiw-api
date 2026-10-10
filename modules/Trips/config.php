<?php

return [
    'page_size' => 20,
    // Private camper details stay visible to the organiser this many days after the trip ends.
    'details_days' => 14,
    // Join code: window length, and how many windows either side still count.
    'code_step_seconds' => 30,
    'code_drift_windows' => 1,
    // How far back a code is recognised as "expired" rather than wrong (windows).
    'code_expired_windows' => 20,
    // Wrong codes allowed per request in `code_lock_seconds` before scanning locks.
    'code_max_attempts' => 5,
    'code_lock_seconds' => 600,
];
