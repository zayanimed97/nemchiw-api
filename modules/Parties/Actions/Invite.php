<?php

namespace Modules\Parties\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\People;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Shared\Support\ContactHash;

final class Invite
{
    public function __construct(private readonly People $people) {}

    /** Invite a person by id, or by phone or email (a placeholder when nobody has that contact yet). */
    public function handle(string $partyId, string $callerId, ?string $userId, ?string $contact): Party
    {
        return DB::transaction(function () use ($partyId, $callerId, $userId, $contact) {
            $party = Parties::lockedOrFail($partyId);
            if ($party->leader_id !== $callerId) {
                throw new ApiException(ApiErrorCode::NotLeader, 'Only the leader can invite');
            }
            if ($party->state !== Party::FORMING) {
                throw new ApiException(ApiErrorCode::PartyLocked, 'The party already asked for a trip');
            }

            $hash = $masked = null;
            if ($userId === null) {
                $normalised = ContactHash::normalise((string) $contact);
                if ($normalised === null) {
                    throw new ApiException(ApiErrorCode::Validation, 'Enter a Tunisian phone number or an email');
                }
                $userId = $this->people->findByContact($normalised);
                if ($userId === null) {
                    $hash = ContactHash::of($normalised);
                    $masked = ContactHash::mask($normalised);
                }
            } elseif ($this->people->publicProfiles([$userId]) === []) {
                throw new ApiException(ApiErrorCode::NotFound, 'Not found');
            }
            if ($userId === $callerId) {
                throw new ApiException(ApiErrorCode::Validation, 'You cannot invite yourself');
            }

            $row = PartyMember::query()->where('party_id', $party->id)
                ->when($hash === null, fn ($q) => $q->where('user_id', $userId), fn ($q) => $q->where('contact_hash', $hash))
                ->lockForUpdate()->first();
            if ($row !== null && in_array($row->state, [PartyMember::INVITED, PartyMember::IN], true)) {
                return $party; // already invited: nothing changes
            }

            $taken = PartyMember::query()->where('party_id', $party->id)
                ->whereIn('state', [PartyMember::INVITED, PartyMember::IN])->count();
            if ($taken + 1 >= (int) config('parties.max_size')) {
                throw new ApiException(ApiErrorCode::PartyFull, 'The party is full');
            }

            $values = ['state' => PartyMember::INVITED, 'invited_at' => now(), 'answered_at' => null, 'reminded_at' => null];
            if ($row === null) {
                PartyMember::query()->create($values + ['party_id' => $party->id, 'user_id' => $userId, 'contact_hash' => $hash, 'contact_masked' => $masked]);
            } else {
                $row->update($values);
            }
            Parties::changed($party, 'invited', $userId, $callerId);

            return $party;
        });
    }
}
