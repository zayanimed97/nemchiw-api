<?php

namespace Modules\Parties\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Parties\Actions\Parties;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Events\RequestChanged;
use Modules\Shared\Events\TripFinished;
use Modules\Trips\Contracts\TripRequests;

/**
 * The party follows its trip request: asked/accepted -> asking, scanned -> going, an ending ->
 * forming again, trip finished -> over. Requests made without a party are ignored. Every move
 * touches `last_activity_at` (through `Parties::changed`), because the idle sweep keys on it.
 */
final class FollowPartyRequest
{
    public function __construct(private readonly TripRequests $trips) {}

    public function requestChanged(RequestChanged $event): void
    {
        if (in_array($event->change, ['member_left', 'member_removed', 'leader_changed'], true)) {
            return; // the party already knows: it raised these itself
        }
        $partyId = $this->trips->partyIdOf($event->requestId);
        if ($partyId === null) {
            return;
        }

        DB::transaction(function () use ($event, $partyId) {
            $party = Party::query()->whereKey($partyId)->lockForUpdate()->first();
            if ($party === null || $party->state === Party::OVER) {
                return; // an ended party stays ended (its request was withdrawn by the ending)
            }

            if (in_array($event->change, RequestChanged::ENDINGS, true)) {
                if ($party->request_id !== $event->requestId) {
                    return; // an older request of this party
                }
                $party->forceFill(['state' => Party::FORMING, 'request_id' => null])->save();
                Parties::changed($party, 'request_ended', null, null);

                return;
            }

            if ($event->change === 'asked' || $event->change === 'accepted') {
                if ($party->state === Party::GOING && $party->request_id === $event->requestId) {
                    return;
                }
                $party->forceFill(['state' => Party::ASKING, 'request_id' => $event->requestId])->save();
                Parties::changed($party, $event->change, null, null);

                return;
            }

            if ($event->change === 'scanned') {
                $party->forceFill(['state' => Party::GOING, 'request_id' => $event->requestId])->save();
                $this->befriend($party, $event->tripId);
                Parties::changed($party, 'scanned', null, null);
            }
        });
    }

    public function tripFinished(TripFinished $event): void
    {
        $requestIds = array_column($this->trips->requestsOfTrip($event->tripId, ['joined']), 'requestId');
        if ($requestIds === []) {
            return;
        }
        DB::transaction(function () use ($requestIds) {
            foreach (Party::query()->whereIn('request_id', $requestIds)->where('state', Party::GOING)->lockForUpdate()->get() as $party) {
                $party->forceFill(['state' => Party::OVER])->save();
                PartyMember::query()->where('party_id', $party->id)->whereNull('user_id')->delete();
                Parties::changed($party, 'ended', null, null);
            }
        });
    }

    /** Everyone who joined together is now a friend "from" the trip's place. */
    private function befriend(Party $party, string $tripId): void
    {
        $place = $this->trips->trip($tripId)['place'] ?? null;
        if ($place === null) {
            return;
        }
        $reason = json_encode(['kind' => 'trip', 'place' => $place]);
        $ids = $party->memberIds();
        foreach ($ids as $user) {
            foreach ($ids as $friend) {
                if ($user === $friend) {
                    continue;
                }
                $row = DB::table('friendships')->where('user_id', $user)->where('friend_id', $friend)->first();
                if ($row === null) {
                    DB::table('friendships')->insert(['user_id' => $user, 'friend_id' => $friend, 'reason' => $reason, 'created_at' => now()]);
                } elseif ((json_decode((string) $row->reason, true)['kind'] ?? null) === 'party') {
                    DB::table('friendships')->where('user_id', $user)->where('friend_id', $friend)->update(['reason' => $reason]);
                }
            }
        }
    }
}
