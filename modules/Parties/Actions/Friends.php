<?php

namespace Modules\Parties\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\People;
use Modules\Shared\Support\Iso;

/** The friendships table: one row per direction. Removing is silent and blocks nothing. */
final class Friends
{
    public function __construct(private readonly People $people) {}

    /** @param array{kind: string, place?: string} $reason */
    public function add(string $a, string $b, array $reason): void
    {
        $now = now();
        foreach ([[$a, $b], [$b, $a]] as [$user, $friend]) {
            DB::table('friendships')->insertOrIgnore([
                'user_id' => $user, 'friend_id' => $friend, 'reason' => json_encode($reason), 'created_at' => $now,
            ]);
        }
    }

    public function remove(string $userId, string $friendId): void
    {
        DB::table('friendships')->where('user_id', $userId)->where('friend_id', $friendId)->delete();
    }

    /** @return list<array{person: array<string, mixed>, reason: mixed, since: string|null}> */
    public function listFor(string $userId): array
    {
        $rows = DB::table('friendships')->where('user_id', $userId)->orderByDesc('created_at')->orderBy('friend_id')->get();
        $people = $this->people->publicProfiles($rows->pluck('friend_id')->all());

        return $rows->filter(fn ($r) => isset($people[$r->friend_id]))->map(fn ($r) => [
            'person' => $people[$r->friend_id],
            'reason' => json_decode($r->reason, true),
            'since' => Iso::format(Carbon::parse($r->created_at)),
        ])->values()->all();
    }
}
