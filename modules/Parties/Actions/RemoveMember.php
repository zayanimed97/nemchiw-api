<?php

namespace Modules\Parties\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Trips\Contracts\TripRequests;

final class RemoveMember
{
    public function __construct(private readonly TripRequests $requests) {}

    /** The leader takes someone out: an invitation, a placeholder or a member who is in. */
    public function handle(string $partyId, string $callerId, string $memberId): Party
    {
        return DB::transaction(function () use ($partyId, $callerId, $memberId) {
            Parties::lockPerson($callerId);
            $party = Parties::lockedOrFail($partyId);
            $row = Parties::leaderRow($party, $callerId, $memberId, [PartyMember::INVITED, PartyMember::IN]);

            if ($row->user_id === null) {
                $row->delete();
                Parties::changed($party, 'removed', null, $callerId);

                return $party;
            }
            $wasIn = $row->state === PartyMember::IN;
            $row->update(['state' => PartyMember::REMOVED]);
            if ($wasIn && Parties::hasOpenRequest($party)) {
                $this->requests->removePartyMember((string) $party->request_id, (string) $row->user_id, $callerId);
            }
            Parties::changed($party, 'removed', (string) $row->user_id, $callerId);

            return $party;
        }, 3);
    }
}
