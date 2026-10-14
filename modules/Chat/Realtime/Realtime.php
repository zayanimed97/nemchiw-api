<?php

namespace Modules\Chat\Realtime;

/** Pushes events to phones that have a conversation open. Never the source of truth. */
interface Realtime
{
    /** @param array<string, mixed> $payload */
    public function publish(string $channel, string $event, array $payload): void;

    /** @return array{auth: string} the Pusher channel signature for this socket */
    public function authorize(string $channel, string $socketId): array;
}
