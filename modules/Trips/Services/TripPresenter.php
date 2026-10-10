<?php

namespace Modules\Trips\Services;

use Illuminate\Support\Collection;
use Modules\Identity\Contracts\People;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Shared\Support\Iso;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Models\TripRequestMember;
use Modules\Trips\Support\RequestStatus;

/** Builds the app's wire shapes for trips (TripSummary, Trip, MyRequest). */
final class TripPresenter
{
    public function __construct(private readonly People $people, private readonly OrganiserRecords $records) {}

    /** @param Collection<int, Trip> $trips @return list<array<string, mixed>> */
    public function summaries(Collection $trips): array
    {
        $organisers = $this->organisers($trips->pluck('organiser_id')->unique()->values()->all());
        $taken = $this->seatsTaken($trips->pluck('id')->all());

        return $trips->map(fn (Trip $trip) => $this->summary($trip, $organisers[$trip->organiser_id], $taken[$trip->id] ?? 0))->values()->all();
    }

    /** @return array<string, mixed> */
    public function detail(Trip $trip, ?string $viewerId): array
    {
        $summary = $this->summaries(collect([$trip]))[0];
        $isOrganiser = $viewerId === $trip->organiser_id;
        $mine = $viewerId === null ? null : $this->latestRequestOf($trip, $viewerId);
        $status = $mine?->status;

        return array_merge($summary, [
            'description' => $trip->description,
            'meetup' => $isOrganiser || in_array($status, [RequestStatus::ACCEPTED, RequestStatus::JOINED], true)
                ? ['url' => $trip->meetup_url, 'at' => Iso::format($trip->meetup_at)]
                : null,
            'myRequest' => $mine === null ? null : $this->myRequest($mine),
            'contact' => $status === RequestStatus::JOINED
                ? ['phone' => $this->records->isCertified($trip->organiser_id)
                    ? ($this->records->publicPhone($trip->organiser_id) ?? $this->people->phone($trip->organiser_id))
                    : $this->people->phone($trip->organiser_id)]
                : null,
        ]);
    }

    /** @return array{id: string, status: string, note: ?string, createdAt: string, acceptedAt: ?string, joinedAt: ?string} */
    public function myRequest(TripRequest $request): array
    {
        return [
            'id' => $request->id,
            'status' => $request->status,
            'note' => $request->note,
            'createdAt' => Iso::format($request->created_at),
            'acceptedAt' => $request->accepted_at === null ? null : Iso::format($request->accepted_at),
            'joinedAt' => $request->joined_at === null ? null : Iso::format($request->joined_at),
        ];
    }

    public function latestRequestOf(Trip $trip, string $userId): ?TripRequest
    {
        return TripRequest::query()->where('trip_id', $trip->id)
            ->whereIn('id', TripRequestMember::query()->where('user_id', $userId)->select('request_id'))
            ->latest('created_at')->latest('id')->first();
    }

    /** @param array{id: string, firstName: ?string, photo: mixed, certified: bool, record: array<string, int>} $organiser */
    private function summary(Trip $trip, array $organiser, int $taken): array
    {
        return [
            'id' => $trip->id,
            'kind' => $trip->kind,
            'title' => $trip->title,
            'destination' => array_merge(['kind' => $trip->destination_kind, 'id' => $trip->destination_id], $trip->destination),
            'startsAt' => Iso::format($trip->starts_at),
            'endsAt' => Iso::format($trip->ends_at),
            'seats' => $trip->seats,
            'seatsTaken' => $taken,
            'priceTnd' => $trip->price_tnd,
            'womenOnly' => $trip->women_only,
            'status' => $trip->status,
            'organiser' => $organiser,
        ];
    }

    /** @param list<string> $ids @return array<string, array<string, mixed>> */
    private function organisers(array $ids): array
    {
        $profiles = $this->people->publicProfiles($ids);
        $cards = $this->records->cards($ids);

        return collect($ids)->mapWithKeys(fn (string $id) => [$id => [
            'id' => $id,
            'firstName' => $profiles[$id]['firstName'] ?? null,
            'photo' => $profiles[$id]['photo'] ?? null,
            'certified' => $cards[$id]['certified'],
            'record' => $cards[$id]['record'],
        ]])->all();
    }

    /** @param list<string> $tripIds @return array<string, int> */
    private function seatsTaken(array $tripIds): array
    {
        return TripRequestMember::query()
            ->join('trip_requests', 'trip_requests.id', '=', 'trip_request_members.request_id')
            ->whereIn('trip_requests.trip_id', $tripIds)
            ->where('trip_requests.status', RequestStatus::JOINED)
            ->groupBy('trip_requests.trip_id')
            ->selectRaw('trip_requests.trip_id as trip_id, count(*) as taken')
            ->pluck('taken', 'trip_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
