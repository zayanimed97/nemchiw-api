<?php

namespace Modules\Trips\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Models\TripRequestMember;
use Modules\Trips\Support\JoinCode;
use Modules\Trips\Support\RequestStatus;
use Modules\Trips\Support\TripStatus;

/** The camper scans the organiser's code: the payment record, the seat, the safety features. */
final class ScanJoinCode
{
    public function __invoke(string $requestId, string $userId, string $code): TripRequest
    {
        $key = "trip-scan:{$requestId}";
        $max = (int) config('trips.code_max_attempts');
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new ApiException(ApiErrorCode::RateLimited, 'Too many wrong codes. Try again in a few minutes', RateLimiter::availableIn($key));
        }

        $outcome = DB::transaction(function () use ($requestId, $userId, $code) {
            $request = TripRequest::query()->findOrFail($requestId);
            $trip = Trip::query()->lockForUpdate()->findOrFail($request->trip_id);
            $request->refresh();
            $members = TripRequestMember::query()->where('request_id', $request->id)->get();
            if (! $members->contains('user_id', $userId)) {
                throw new ApiException(ApiErrorCode::Forbidden, 'This is not your request');
            }
            if ($request->status !== RequestStatus::ACCEPTED || $trip->status !== TripStatus::PUBLISHED || ! $trip->starts_at->isFuture()) {
                throw new ApiException(ApiErrorCode::NotAccepted, 'This request is no longer open');
            }
            $check = JoinCode::check(base64_decode((string) $request->code_secret, true) ?: '', $code, now()->getTimestamp());
            if ($check !== 'ok') {
                return $check; // counted outside the transaction
            }
            if ($trip->joinedCount() + $members->count() > $trip->seats) {
                throw new ApiException(ApiErrorCode::TripFull, 'The trip is full');
            }
            $request->forceFill(['status' => RequestStatus::JOINED, 'joined_at' => now(), 'code_secret' => null])->save();
            TripRequestMember::query()->where('request_id', $request->id)->update(['scanned_at' => now()]);

            return $request;
        });

        if (is_string($outcome)) {
            RateLimiter::hit($key, (int) config('trips.code_lock_seconds'));
            throw $outcome === 'expired'
                ? new ApiException(ApiErrorCode::CodeExpired, 'That code has expired')
                : new ApiException(ApiErrorCode::InvalidCode, 'That code is not right');
        }

        return $outcome;
    }
}
