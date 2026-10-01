<?php

namespace Modules\Verification\Actions;

use Illuminate\Support\Facades\DB;

/** Replays older than the 300 s signature window are refused anyway; a day is plenty. */
final class PruneWebhookEvents
{
    public function __invoke(): int
    {
        return DB::table('webhook_events')->where('received_at', '<', now()->subDay())->delete();
    }
}
