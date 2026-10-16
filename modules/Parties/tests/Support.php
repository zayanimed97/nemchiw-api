<?php

use Modules\Identity\Models\User;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;

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
