<?php

use Modules\Identity\Models\User;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Trips\Contracts\TripRequests;

function partyAs($test, User $user): mixed
{
    return $test->withToken(tokenFor($user));
}

/** A party the leader created through the API. */
function makeParty($test, User $leader): Party
{
    $id = partyAs($test, $leader)->postJson('/api/v1/parties')->assertCreated()->json('id');

    return Party::query()->findOrFail($id);
}

/** A person invited by id who has answered `in`. */
function joinParty($test, Party $party, User $member): void
{
    partyAs($test, User::find($party->leader_id))->postJson("/api/v1/parties/{$party->id}/invites", ['userId' => $member->id])->assertOk();
    partyAs($test, $member)->postJson("/api/v1/parties/{$party->id}/accept")->assertOk();
}

function memberRow(Party $party, User $user): ?PartyMember
{
    return PartyMember::query()->where('party_id', $party->id)->where('user_id', $user->id)->first();
}

/** A TripRequests stand-in that records the party calls Parties makes (Task 5 links real requests). */
function fakeTripRequests(): object
{
    $fake = new class implements TripRequests
    {
        /** @var list<array{string, mixed}> */
        public array $calls = [];

        public function context(array $requestIds): array
        {
            return [];
        }

        public function trip(string $tripId): ?array
        {
            return null;
        }

        public function requestsOfTrip(string $tripId, array $statuses): array
        {
            return [];
        }

        public function openRequestFor(string $tripId, string $userId): ?string
        {
            return null;
        }

        public function removePartyMember(string $requestId, string $userId, string $by): void
        {
            $this->calls[] = ['removePartyMember', [$requestId, $userId, $by]];
        }

        public function changeLeader(string $requestId, string $leaderId): void
        {
            $this->calls[] = ['changeLeader', [$requestId, $leaderId]];
        }

        public function withdrawForParty(string $requestId): void
        {
            $this->calls[] = ['withdrawForParty', [$requestId]];
        }
    };
    app()->instance(TripRequests::class, $fake);

    return $fake;
}

/** A member who answered `in` at a given moment, so the handover order is fixed. */
function joinPartyAt($test, Party $party, User $member, string $answeredAt): void
{
    joinParty($test, $party, $member);
    memberRow($party, $member)->forceFill(['answered_at' => $answeredAt])->save();
}
