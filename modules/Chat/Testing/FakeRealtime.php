<?php

namespace Modules\Chat\Testing;

use Modules\Chat\Realtime\Realtime;

/** Records what would be published; signs channels predictably. */
final class FakeRealtime implements Realtime
{
    /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
    public static array $published = [];

    public static bool $fail = false;

    public static function reset(): void
    {
        self::$published = [];
        self::$fail = false;
    }

    public function publish(string $channel, string $event, array $payload): void
    {
        if (self::$fail) {
            throw new \RuntimeException('Ably is down');
        }
        self::$published[] = [$channel, $event, $payload];
    }

    public function authorize(string $channel, string $socketId): array
    {
        return ['auth' => "fake:{$channel}:{$socketId}"];
    }
}
