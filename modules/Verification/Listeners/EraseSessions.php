<?php

namespace Modules\Verification\Listeners;

use Modules\Identity\Events\AccountDeleting;
use Modules\Verification\Jobs\EraseDiditSession;
use Modules\Verification\Models\VerificationSession;

final class EraseSessions
{
    public function handle(AccountDeleting $event): void
    {
        $sessions = VerificationSession::query()->where('user_id', $event->userId)->pluck('session_id');
        foreach ($sessions as $sessionId) {
            EraseDiditSession::dispatch($sessionId)->afterCommit();
        }
        VerificationSession::query()->where('user_id', $event->userId)->delete();
    }
}
