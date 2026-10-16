<?php

namespace Modules\Parties\Actions;

use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Trips\Contracts\TripRequests;

/**
 * One person leaves a party. Call inside DB::transaction with the person and then the party
 * row locked. A member is marked `left`. A leader hands the lead to the `in` member who
 * answered first; with nobody left the party ends (placeholders forgotten, open request
 * withdrawn). While the party is asking or going, the request follows.
 */
final class LeaveParty
{
    public function __construct(private readonly TripRequests $requests) {}

    /** @param string $by 'self', or the id of whoever acted */
    public function __invoke(Party $party, string $userId, string $by = 'self'): void
    {
        $byId = $by === 'self' ? $userId : $by;

        if ($party->leader_id !== $userId) {
            $row = PartyMember::query()->where('party_id', $party->id)->where('user_id', $userId)
                ->where('state', PartyMember::IN)->lockForUpdate()->first();
            if ($row === null) {
                return;
            }
            $row->update(['state' => PartyMember::LEFT]);
            if (Parties::hasOpenRequest($party)) {
                $this->requests->removePartyMember((string) $party->request_id, $userId, $byId);
            }
            Parties::changed($party, 'left', $userId, $byId);

            return;
        }

        $next = PartyMember::query()->where('party_id', $party->id)->where('state', PartyMember::IN)
            ->whereNotNull('user_id')->orderBy('answered_at')->orderBy('id')->lockForUpdate()->first();

        if ($next === null) {
            Parties::finish($party, 'ended', $userId, $byId);

            return;
        }

        $newLeader = (string) $next->user_id;
        $next->delete(); // the leader is not a member row
        $party->forceFill(['leader_id' => $newLeader])->save();
        if (Parties::hasOpenRequest($party)) {
            $this->requests->changeLeader((string) $party->request_id, $newLeader);
            $this->requests->removePartyMember((string) $party->request_id, $userId, $byId);
        }
        Parties::changed($party, 'left', $userId, $byId);
        Parties::changed($party, 'leader_changed', $newLeader, $byId);
    }
}
