<?php

namespace Modules\Trips\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\People;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Shared\Events\TripEdited;
use Modules\Shared\Places\PlaceDirectory;
use Modules\Shared\Places\PlaceStatus;
use Modules\Trips\Models\Trip;
use Modules\Trips\Support\TripKind;
use Modules\Trips\Support\TripStatus;

final class SaveTrip
{
    private const LOCKED = ['kind', 'startsAt', 'endsAt', 'priceTnd', 'destination'];

    public function __construct(
        private readonly People $people,
        private readonly OrganiserRecords $records,
        private readonly PlaceDirectory $places,
    ) {}

    /** @param array<string, mixed> $data validated SaveTripRequest data */
    public function create(string $organiserId, array $data): Trip
    {
        $this->checkHost($organiserId, $data['kind']);
        $this->checkRules($data);
        [$snapshot, $status] = $this->destination($data['destination'], $organiserId);

        return Trip::query()->create(array_merge($this->columns($data), [
            'organiser_id' => $organiserId,
            'destination_kind' => $data['destination']['kind'],
            'destination_id' => $data['destination']['id'],
            'destination' => $snapshot,
            'status' => $status,
        ]));
    }

    /** @param array<string, mixed> $data */
    public function update(Trip $trip, string $actorId, array $data): Trip
    {
        if ($trip->organiser_id !== $actorId) {
            throw new ApiException(ApiErrorCode::Forbidden, 'Only the organiser can change this trip');
        }
        if (! in_array($trip->status, [TripStatus::DRAFT, TripStatus::PUBLISHED], true)) {
            throw new ApiException(ApiErrorCode::TripNotOpen, 'This trip is closed');
        }

        return DB::transaction(function () use ($trip, $actorId, $data) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $joined = $trip->joinedCount();
            if ($joined > 0) {
                foreach (self::LOCKED as $field) {
                    if (array_key_exists($field, $data) && $this->changes($trip, $field, $data[$field])) {
                        throw new ApiException(ApiErrorCode::TripLocked, 'Dates, price, kind and place are fixed once someone has joined');
                    }
                }
            }
            if (array_key_exists('seats', $data) && $data['seats'] < max(1, $joined)) {
                throw ValidationException::withMessages(['seats' => "At least {$joined} seats are already paid"]);
            }
            $merged = array_merge($this->current($trip), $data);
            if (array_key_exists('kind', $data)) {
                $this->checkHost($actorId, $merged['kind']);
            }
            $this->checkRules($merged, array_keys($data));
            $columns = $this->columns(Arr::only($merged, array_keys($data)));
            if (array_key_exists('destination', $data)) {
                [$snapshot, $status] = $this->destination($data['destination'], $actorId);
                $columns += ['destination_kind' => $data['destination']['kind'], 'destination_id' => $data['destination']['id'], 'destination' => $snapshot, 'status' => $status];
            }
            $edited = array_values(array_filter(
                ['meetupAt', 'meetupUrl'],
                fn (string $field) => array_key_exists($field, $data) && $this->changes($trip, $field, $data[$field]),
            ));
            $trip->fill($columns)->save();
            if ($edited !== [] && $trip->status === TripStatus::PUBLISHED) {
                event(new TripEdited($trip->id, $trip->organiser_id, $edited));
            }

            return $trip;
        });
    }

    private function checkHost(string $userId, string $kind): void
    {
        if (! $this->people->tripReadiness($userId)['ready']) {
            throw new ApiException(ApiErrorCode::NotTripReady, 'Finish your trip-ready profile first');
        }
        if (in_array($kind, TripKind::CERTIFIED, true) && ! $this->records->isCertified($userId)) {
            throw new ApiException(ApiErrorCode::NotCertified, 'Camping trips and pro hikes need a certified organiser');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $changed  keys sent on PATCH; null on create. An unchanged start may be in the past,
     *                                      and an older multi-day hike keeps its dates until they change.
     */
    private function checkRules(array $data, ?array $changed = null): void
    {
        $now = CarbonImmutable::now();
        $starts = CarbonImmutable::parse($data['startsAt']);
        $errors = [];
        if (($changed === null || in_array('startsAt', $changed, true)) && $starts->lessThanOrEqualTo($now)) {
            $errors['startsAt'] = 'The trip must start in the future';
        }
        if (CarbonImmutable::parse($data['endsAt'])->lessThanOrEqualTo($starts)) {
            $errors['endsAt'] = 'The trip must end after it starts';
        }
        $dated = $changed === null || array_intersect(['kind', 'startsAt', 'endsAt'], $changed) !== [];
        $tunis = fn (string $iso) => CarbonImmutable::parse($iso)->setTimezone('Africa/Tunis')->toDateString();
        if ($dated && $data['kind'] !== TripKind::CAMPING && $tunis($data['startsAt']) !== $tunis($data['endsAt'])) {
            $errors['endsAt'] = 'A hike ends on the day it starts';
        }
        if (CarbonImmutable::parse($data['meetupAt'])->greaterThan($starts)) {
            $errors['meetupAt'] = 'Meet before the trip starts';
        }
        $community = $data['kind'] === TripKind::HIKE_COMMUNITY;
        if ($community && $data['priceTnd'] !== null) {
            $errors['priceTnd'] = 'Community hikes are free';
        }
        if (! $community && $data['priceTnd'] === null) {
            $errors['priceTnd'] = 'Set a price per person';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array{kind: string, id: string} $destination @return array{0: array<string, mixed>, 1: string} */
    private function destination(array $destination, string $hostId): array
    {
        $place = $this->places->find($destination['kind'], $destination['id']);
        $usable = $place !== null && ($place['status'] === PlaceStatus::LIVE
            || ($place['status'] === PlaceStatus::PENDING && $place['createdBy'] === $hostId));
        if (! $usable) {
            throw ValidationException::withMessages(['destination' => 'Choose a place on the map']);
        }
        $snapshot = Arr::only($place, ['name', 'governorate', 'latitude', 'longitude', 'cover']);

        return [$snapshot, $place['status'] === PlaceStatus::LIVE ? TripStatus::PUBLISHED : TripStatus::DRAFT];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function columns(array $data): array
    {
        $map = ['kind' => 'kind', 'title' => 'title', 'description' => 'description', 'meetupUrl' => 'meetup_url',
            'meetupAt' => 'meetup_at', 'startsAt' => 'starts_at', 'endsAt' => 'ends_at', 'seats' => 'seats',
            'priceTnd' => 'price_tnd', 'womenOnly' => 'women_only'];
        $columns = [];
        foreach ($map as $key => $column) {
            if (array_key_exists($key, $data)) {
                $columns[$column] = in_array($key, ['meetupAt', 'startsAt', 'endsAt'], true)
                    ? CarbonImmutable::parse($data[$key])->utc()
                    : $data[$key];
            }
        }

        return $columns;
    }

    /** @return array<string, mixed> the trip as request data, for cross-field checks on PATCH */
    private function current(Trip $trip): array
    {
        return [
            'kind' => $trip->kind, 'meetupAt' => $trip->meetup_at->toIso8601String(),
            'startsAt' => $trip->starts_at->toIso8601String(), 'endsAt' => $trip->ends_at->toIso8601String(),
            'priceTnd' => $trip->price_tnd,
        ];
    }

    private function changes(Trip $trip, string $field, mixed $value): bool
    {
        return match ($field) {
            'kind' => $value !== $trip->kind,
            'priceTnd' => $value !== $trip->price_tnd,
            'startsAt' => ! CarbonImmutable::parse($value)->equalTo($trip->starts_at),
            'endsAt' => ! CarbonImmutable::parse($value)->equalTo($trip->ends_at),
            'destination' => $value['kind'] !== $trip->destination_kind || $value['id'] !== $trip->destination_id,
            'meetupAt' => ! CarbonImmutable::parse($value)->equalTo($trip->meetup_at),
            'meetupUrl' => $value !== $trip->meetup_url,
        };
    }
}
