<?php

namespace Modules\Chat\Realtime;

use Pusher\Pusher;

/** Ably through its Pusher protocol adapter (host main.pusher.ably.net). */
final class PusherRealtime implements Realtime
{
    public function __construct(private readonly Pusher $pusher) {}

    public function publish(string $channel, string $event, array $payload): void
    {
        $this->pusher->trigger($channel, $event, $payload);
    }

    public function authorize(string $channel, string $socketId): array
    {
        /** @var array{auth: string} */
        return json_decode($this->pusher->authorizeChannel($channel, $socketId), true, flags: JSON_THROW_ON_ERROR);
    }
}
