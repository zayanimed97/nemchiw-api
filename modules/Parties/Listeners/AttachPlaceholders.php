<?php

namespace Modules\Parties\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Parties\Actions\Parties;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Events\ContactConfirmed;

/**
 * A phone or email became someone's: every placeholder for it turns into a normal invitation.
 * Safe to run twice for the same contact (providers confirm again at each sign-in): the
 * second run finds no placeholder. A party that already asked for a trip still gets the
 * invitation; accepting it is refused as `party_locked`.
 */
final class AttachPlaceholders
{
    public function handle(ContactConfirmed $event): void
    {
        $partyIds = PartyMember::query()->where('contact_hash', $event->contactHash)->whereNull('user_id')->pluck('party_id');

        foreach ($partyIds as $partyId) {
            DB::transaction(function () use ($partyId, $event) {
                $party = Party::query()->whereKey($partyId)->lockForUpdate()->first();
                $row = PartyMember::query()->where('party_id', $partyId)->where('contact_hash', $event->contactHash)
                    ->whereNull('user_id')->lockForUpdate()->first();
                if ($party === null || $row === null) {
                    return;
                }
                $already = $party->leader_id === $event->userId
                    || PartyMember::query()->where('party_id', $partyId)->where('user_id', $event->userId)->exists();
                if ($party->state === Party::OVER || $already) {
                    $row->delete(); // nobody to invite: the leader themselves, or someone already asked

                    return;
                }
                $row->forceFill(['user_id' => $event->userId, 'contact_hash' => null, 'contact_masked' => null])->save();
                Parties::changed($party, 'invited', $event->userId, null);
            });
        }
    }
}
