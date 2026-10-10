<?php

namespace Modules\Trips\Actions;

use Closure;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\People;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
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
            $blocking = TripRequest::query()->where('trip_id', $trip->id)
                ->whereIn('id', TripRequestMember::query()->where('user_id', $userId)->select('request_id'))
                ->whereNotIn('status', RequestStatus::MAY_REQUEST_AGAIN)
                ->exists();
            if ($blocking) {
                throw new ApiException(ApiErrorCode::AlreadyRequested, 'You already asked to join this trip');
            }
            $request = TripRequest::query()->create([
                'trip_id' => $trip->id, 'leader_id' => $userId, 'note' => $note, 'status' => RequestStatus::PENDING,
            ]);
            TripRequestMember::query()->create(['request_id' => $request->id, 'user_id' => $userId]);

            return $request;
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
            $request->forceFill(['withdrawn_from' => $request->status])->save();
            $this->end($request, RequestStatus::WITHDRAWN, 'camper');
        });
    }

    public function cancel(TripRequest $request, string $actorId): TripRequest
    {
        return $this->onRequest($request, $actorId, 'organiser', [RequestStatus::ACCEPTED, RequestStatus::JOINED], function (TripRequest $request, Trip $trip) {
            $this->end($request, RequestStatus::CANCELLED, 'organiser');
            $this->records->bump($trip->organiser_id, 'cancelled_campers');
        });
    }

    /** Ends a request: status, who, when. The code secret goes with it. */
    public function end(TripRequest $request, string $status, string $by): void
    {
        $request->forceFill(['status' => $status, 'ended_at' => now(), 'ended_by' => $by, 'code_secret' => null])->save();
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
