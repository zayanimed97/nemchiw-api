<?php

namespace Modules\Trips\Actions;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\People;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Parties\Contracts\PartyRoster;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Shared\Events\RequestChanged;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Models\TripRequestMember;
use Modules\Trips\Support\RequestStatus;
use Modules\Trips\Support\TripStatus;

/**
 * Every change to a request. Each runs in a transaction that locks the trip row, so two
 * changes to the same trip (two scans for the last seat, a cancel during an accept) queue.
 */
final class RequestTransitions
{
    public function __construct(private readonly People $people, private readonly OrganiserRecords $records) {}

    public function ask(Trip $trip, string $userId, ?string $note): TripRequest
    {
        return $this->locked($trip, function (Trip $trip) use ($userId, $note) {
            $this->assertOpen($trip);
            if ($trip->organiser_id === $userId) {
                throw new ApiException(ApiErrorCode::OwnTrip, 'You organise this trip');
            }
            $readiness = $this->people->tripReadiness($userId);
            if (! $readiness['ready']) {
                throw new ApiException(ApiErrorCode::NotTripReady, 'Finish your trip-ready profile first');
            }
            if ($trip->women_only && ($readiness['gender'] !== 'female' || ! $readiness['verifiedPhoto'])) {
                throw new ApiException(ApiErrorCode::WomenOnlyRequired, 'This trip is for women only');
            }
            if ($this->blockingRequests($trip->id, [$userId])->exists()) {
                throw new ApiException(ApiErrorCode::AlreadyRequested, 'You already asked to join this trip');
            }
            $request = TripRequest::query()->create([
                'trip_id' => $trip->id, 'leader_id' => $userId, 'note' => $note, 'status' => RequestStatus::PENDING,
            ]);
            TripRequestMember::query()->create(['request_id' => $request->id, 'user_id' => $userId]);
            event(new RequestChanged($request->id, $trip->id, $userId, $trip->organiser_id, 'asked', 'camper', $note));

            return $request;
        });
    }

    /**
     * The leader asks for the whole party. Order of refusals: not the leader, party locked
     * (inside `forAsk`, which locks person then party), trip closed, someone not ready, no
     * seats for everyone. The trip row is locked last.
     */
    public function askAsParty(Trip $trip, string $leaderId, string $partyId, ?string $note): TripRequest
    {
        return DB::transaction(function () use ($trip, $leaderId, $partyId, $note) {
            $roster = app(PartyRoster::class)->forAsk($partyId, $leaderId, $trip->id); // lazy: Parties depends on Trips

            return $this->locked($trip, function (Trip $trip) use ($roster, $leaderId, $partyId, $note) {
                $this->assertOpen($trip);
                $memberIds = $roster['memberIds'];
                $taken = $this->blockingRequests($trip->id, $memberIds)->exists();
                if ($roster['blockers'] !== [] || $taken || in_array($trip->organiser_id, $memberIds, true)) {
                    throw new ApiException(ApiErrorCode::PartyNotReady, 'Someone in the party is not ready');
                }
                if ($trip->joinedCount() + count($memberIds) > $trip->seats) {
                    throw new ApiException(ApiErrorCode::TripFull, 'The trip does not have seats for the whole party');
                }
                $request = TripRequest::query()->create([
                    'trip_id' => $trip->id, 'leader_id' => $leaderId, 'note' => $note, 'status' => RequestStatus::PENDING, 'party_id' => $partyId,
                ]);
                foreach ($memberIds as $memberId) {
                    TripRequestMember::query()->create(['request_id' => $request->id, 'user_id' => $memberId]);
                }
                app(PartyRoster::class)->markAsking($partyId, $request->id);
                event(new RequestChanged($request->id, $trip->id, $leaderId, $trip->organiser_id, 'asked', 'camper', $note));

                return $request;
            });
        });
    }

    public function accept(TripRequest $request, string $actorId): TripRequest
    {
        return $this->onRequest($request, $actorId, 'organiser', [RequestStatus::PENDING], function (TripRequest $request, Trip $trip) {
            $this->assertOpen($trip);
            $request->forceFill([
                'status' => RequestStatus::ACCEPTED,
                'accepted_at' => now(),
                'code_secret' => base64_encode(random_bytes(32)),
            ])->save();
            event(new RequestChanged($request->id, $trip->id, $request->leader_id, $trip->organiser_id, 'accepted', 'organiser'));
        });
    }

    public function decline(TripRequest $request, string $actorId): TripRequest
    {
        return $this->onRequest($request, $actorId, 'organiser', [RequestStatus::PENDING], function (TripRequest $request) {
            $this->end($request, RequestStatus::DECLINED, 'organiser');
        });
    }

    public function withdraw(TripRequest $request, string $actorId): TripRequest
    {
        return $this->onRequest($request, $actorId, 'leader', RequestStatus::OPEN, function (TripRequest $request) {
            if ($request->party_id !== null && $request->status === RequestStatus::JOINED) {
                // after the scan, members leave the party one by one; the leader can't pull them all out
                throw new ApiException(ApiErrorCode::PartyLocked, 'The party already joined this trip');
            }
            $request->forceFill(['withdrawn_from' => $request->status])->save();
            $this->end($request, RequestStatus::WITHDRAWN, 'camper');
        });
    }

    public function cancel(TripRequest $request, string $actorId): TripRequest
    {
        return $this->onRequest($request, $actorId, 'organiser', [RequestStatus::ACCEPTED, RequestStatus::JOINED], function (TripRequest $request, Trip $trip) {
            $this->end($request, RequestStatus::CANCELLED, 'organiser');
            $this->records->bump($trip->organiser_id, 'cancelled_campers', max(1, $request->members()->count()));
        });
    }

    /** The party ended: an open request is withdrawn (the camper side), whatever it was. */
    public function withdrawForParty(string $requestId): void
    {
        $this->lockedRequest($requestId, function (TripRequest $request) {
            $request->forceFill(['withdrawn_from' => $request->status])->save();
            $this->end($request, RequestStatus::WITHDRAWN, 'camper');
        });
    }

    /**
     * A member leaves, or `$by` removes them. Nobody left: the request is withdrawn. A joined
     * member's seat frees because only joined members hold seats.
     */
    public function removeMember(string $requestId, string $userId, string $by): void
    {
        $this->lockedRequest($requestId, function (TripRequest $request, Trip $trip) use ($userId, $by) {
            $removed = TripRequestMember::query()->where('request_id', $request->id)->where('user_id', $userId)->delete();
            if ($removed === 0) {
                return;
            }
            if (! TripRequestMember::query()->where('request_id', $request->id)->exists()) {
                $request->forceFill(['withdrawn_from' => $request->status])->save();
                $this->end($request, RequestStatus::WITHDRAWN, 'camper');

                return;
            }
            event(new RequestChanged($request->id, $trip->id, $request->leader_id, $trip->organiser_id, $by === $userId ? 'member_left' : 'member_removed', 'camper', $userId));
        });
    }

    public function changeLeader(string $requestId, string $leaderId): void
    {
        $this->lockedRequest($requestId, function (TripRequest $request, Trip $trip) use ($leaderId) {
            $request->forceFill(['leader_id' => $leaderId])->save();
            event(new RequestChanged($request->id, $trip->id, $leaderId, $trip->organiser_id, 'leader_changed', 'camper', $leaderId));
        });
    }

    /** @param Closure(TripRequest, Trip): void $change  runs only on an open request */
    private function lockedRequest(string $requestId, Closure $change): void
    {
        $tripId = TripRequest::query()->whereKey($requestId)->value('trip_id');
        if ($tripId === null) {
            return;
        }
        DB::transaction(function () use ($requestId, $tripId, $change) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($tripId);
            $request = TripRequest::query()->find($requestId);
            if ($request === null || ! in_array($request->status, RequestStatus::OPEN, true)) {
                return;
            }
            $change($request, $trip);
        });
    }

    /**
     * Ends a request: status, who, when. The code secret goes with it. `$change` names the
     * event when it differs from the status (a trip cancellation ends requests as declined
     * or cancelled, and tells people the trip was cancelled).
     */
    public function end(TripRequest $request, string $status, string $by, ?string $change = null): void
    {
        $request->forceFill(['status' => $status, 'ended_at' => now(), 'ended_by' => $by, 'code_secret' => null])->save();
        $organiserId = (string) Trip::query()->whereKey($request->trip_id)->value('organiser_id');
        event(new RequestChanged($request->id, $request->trip_id, $request->leader_id, $organiserId, $change ?? $status, $by));
    }

    /**
     * The one "already asked" rule: requests for this trip that any of these people is on and
     * that don't let them ask again (anything but declined, withdrawn, lapsed). Readiness
     * reports the same thing as `already_requested`.
     *
     * @param  list<string>  $userIds
     * @return Builder<TripRequest>
     */
    public function blockingRequests(string $tripId, array $userIds): Builder
    {
        return TripRequest::query()->where('trip_id', $tripId)
            ->whereIn('id', TripRequestMember::query()->whereIn('user_id', $userIds)->select('request_id'))
            ->whereNotIn('status', RequestStatus::MAY_REQUEST_AGAIN);
    }

    public function assertOpen(Trip $trip): void
    {
        if ($trip->status !== TripStatus::PUBLISHED || ! $trip->starts_at->isFuture()) {
            throw new ApiException(ApiErrorCode::TripNotOpen, 'This trip is not taking requests');
        }
    }

    /**
     * @param  'organiser'|'leader'  $who
     * @param  list<string>  $from  statuses the change may start from
     * @param  Closure(TripRequest, Trip): void  $change
     */
    private function onRequest(TripRequest $request, string $actorId, string $who, array $from, Closure $change): TripRequest
    {
        return $this->locked($request->trip, function (Trip $trip) use ($request, $actorId, $who, $from, $change) {
            $request = TripRequest::query()->findOrFail($request->id);
            $allowed = $who === 'organiser' ? $trip->organiser_id === $actorId : $request->leader_id === $actorId;
            if (! $allowed) {
                throw new ApiException(ApiErrorCode::Forbidden, 'This is not your request to change');
            }
            if (! in_array($request->status, $from, true)) {
                throw new ApiException(ApiErrorCode::TripNotOpen, 'This request is no longer open');
            }
            $change($request, $trip);

            return $request;
        });
    }

    /**
     * @template T
     *
     * @param  Closure(Trip): T  $work
     * @return T
     */
    private function locked(Trip $trip, Closure $work): mixed
    {
        return DB::transaction(fn () => $work(Trip::query()->lockForUpdate()->findOrFail($trip->id)));
    }
}
