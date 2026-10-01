<?php

namespace Modules\Verification\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Verification\Contracts\IdentityVerifier;

/** Erases a session (and its face data) at Didit after an account is deleted. Retries for a week. */
final class EraseDiditSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 0; // bounded by retryUntil()

    public function __construct(public readonly string $sessionId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 7200];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(7);
    }

    /** Face data may still be at Didit: someone must erase it by hand. */
    public function failed(\Throwable $e): void
    {
        Log::error('Didit erase gave up: erase this session by hand in the Didit console', ['session_id' => $this->sessionId]);
    }

    public function handle(IdentityVerifier $verifier): void
    {
        $verifier->deleteSession($this->sessionId);
    }
}
