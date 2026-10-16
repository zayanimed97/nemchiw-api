<?php

namespace Modules\Parties\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Shared\Events\PartyChanged;
use Modules\Trips\Contracts\TripRequests;

/** Locks and lookups every party action shares. Call inside DB::transaction. */
final class Parties
{
    /**
     * Serialises everything one person does to their parties. The user row is the mutex, so
     * two requests from the same person (or a create racing an accept) queue up.
     */
    public static function lockPerson(string $userId): void
    {
        DB::table('users')->where('id', $userId)->lockForUpdate()->value('id');
    }

    /** The party this person is in right now: leading it, or an `in` member, and not over. */
    public static function openFor(string $userId, bool $lock = true): ?Party
    {
        $ids = Party::query()->where('state', '!=', Party::OVER)->where(fn ($q) => $q
            ->where('leader_id', $userId)
            ->orWhereIn('id', PartyMember::query()->where('user_id', $userId)->where('state', PartyMember::IN)->select('party_id')))
            ->orderBy('id')->pluck('id');

        $query = Party::query()->whereIn('id', $ids)->where('state', '!=', Party::OVER)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** Still leading this party, or an `in` member of it. */
    public static function isIn(Party $party, string $userId): bool
    {
        return $party->leader_id === $userId
            || PartyMember::query()->where('party_id', $party->id)->where('user_id', $userId)->where('state', PartyMember::IN)->exists();
    }

    public static function lockedOrFail(string $partyId): Party
    {
        $party = Party::query()->whereKey(strtolower($partyId))->lockForUpdate()->first();
        if ($party === null || $party->state === Party::OVER) {
            throw new ApiException(ApiErrorCode::NotFound, 'Not found');
        }

        return $party;
    }

    public static function changed(Party $party, string $change, ?string $userId, ?string $byId): void
    {
        $party->touchActivity();
        event(new PartyChanged($party->id, $party->request_id, $change, $userId, $byId, $party->leader_id, $party->memberIds()));
    }

    /**
     * The party is over: its open request is withdrawn and the placeholders are forgotten.
     * Call inside DB::transaction with the party row locked.
     */
    public static function finish(Party $party, string $change, ?string $userId, ?string $byId): void
    {
        if ($party->request_id !== null) {
            app(TripRequests::class)->withdrawForParty($party->request_id);
        }
        $party->forceFill(['state' => Party::OVER])->save();
        PartyMember::query()->where('party_id', $party->id)->whereNull('user_id')->delete();
        self::changed($party, $change, $userId, $byId);
    }

    /** Does this party have a trip request that members must follow (asking or going)? */
    public static function hasOpenRequest(Party $party): bool
    {
        return $party->request_id !== null && in_array($party->state, [Party::ASKING, Party::GOING], true);
    }

    /**
     * A member row the leader acts on (party locked, caller already person-locked). Not the
     * leader: 403. No such row in one of `$states`: 404.
     *
     * @param  list<string>  $states
     */
    public static function leaderRow(Party $party, string $callerId, string $memberId, array $states): PartyMember
    {
        if ($party->leader_id !== $callerId) {
            throw new ApiException(ApiErrorCode::NotLeader, 'Only the leader can do this');
        }
        $row = PartyMember::query()->where('party_id', $party->id)->whereKey(strtolower($memberId))
            ->whereIn('state', $states)->lockForUpdate()->first();
        if ($row === null) {
            throw new ApiException(ApiErrorCode::NotFound, 'Not found');
        }

        return $row;
    }
}
