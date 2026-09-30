<?php

return [
    // Rows per sync page. When a page is full, serverTime is the cursor for the next one.
    'page_size' => 1000,
    // How far behind "now" a partial page's serverTime is, so late commits are not skipped.
    'cursor_lag_seconds' => 120,
];
