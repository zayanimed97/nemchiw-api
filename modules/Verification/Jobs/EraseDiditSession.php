<?php

namespace Modules\Verification\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Verification\Contracts\IdentityVerifier;

/** Erases a session (and its face data) at Didit after an account is deleted. Retries for about a day. */
final class EraseDiditSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 12;

    public function __construct(public readonly string $sessionId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 7200];
    }

    public function handle(IdentityVerifier $verifier): void
    {
        $verifier->deleteSession($this->sessionId);
    }
}
