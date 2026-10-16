<?php

namespace Modules\Parties\Actions;

use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;

/**
 * One person leaves a party. Call inside DB::transaction with the party row locked.
 * A member is marked `left`. A leader hands the lead to the `in` member who answered first;
 * with nobody left the party ends (placeholders are forgotten). Task 3 builds the routes,
 * removal and the open request on top of this.
 */
final class LeaveParty
{
    public function handle(Party $party, string $userId): void
    {
        if ($party->leader_id !== $userId) {
            $row = PartyMember::query()->where('party_id', $party->id)->where('user_id', $userId)
                ->where('state', PartyMember::IN)->lockForUpdate()->first();
            if ($row === null) {
                return;
            }
            $row->update(['state' => PartyMember::LEFT]);
            Parties::changed($party, 'left', $userId, $userId);

            return;
        }

        $next = PartyMember::query()->where('party_id', $party->id)->where('state', PartyMember::IN)
            ->whereNotNull('user_id')->orderBy('answered_at')->orderBy('id')->lockForUpdate()->first();

        if ($next === null) {
            $party->forceFill(['state' => Party::OVER])->save();
            PartyMember::query()->where('party_id', $party->id)->whereNotNull('contact_hash')
                ->update(['contact_hash' => null, 'contact_masked' => null]);
            Parties::changed($party, 'ended', $userId, $userId);

            return;
        }

        $newLeader = (string) $next->user_id;
        $next->delete(); // the leader is not a member row
        $party->forceFill(['leader_id' => $newLeader])->save();
        Parties::changed($party, 'left', $userId, $userId);
        Parties::changed($party, 'leader_changed', $newLeader, $userId);
    }
}
