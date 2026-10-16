<?php

namespace Modules\Parties\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Parties\Models\Party;

/** A forming party nobody touched for 30 days is over. A party that asked for a trip follows its request instead. */
final class EndIdleParties
{
    public function __invoke(): int
    {
        $cutoff = now()->subDays((int) config('parties.idle_days'));
        $ended = 0;

        Party::query()->where('state', Party::FORMING)
            ->where(fn ($q) => $q->where('last_activity_at', '<', $cutoff)->orWhere(fn ($q) => $q->whereNull('last_activity_at')->where('created_at', '<', $cutoff)))
            ->orderBy('id')->get(['id', 'leader_id'])
            ->each(function (Party $candidate) use ($cutoff, &$ended) {
                DB::transaction(function () use ($candidate, $cutoff, &$ended) {
                    Parties::lockPerson($candidate->leader_id); // person first, then the party
                    $party = Party::query()->whereKey($candidate->id)->lockForUpdate()->first();
                    $touched = $party?->last_activity_at ?? $party?->created_at;
                    if ($party === null || $party->state !== Party::FORMING || $touched === null || $touched->gte($cutoff)) {
                        return; // it moved since the query
                    }
                    Parties::finish($party, 'auto_ended', null, null);
                    $ended++;
                });
            });

        return $ended;
    }
}
