<?php

namespace Modules\Organisers\Services;

use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Organisers\Models\Organiser;

final class EloquentOrganiserRecords implements OrganiserRecords
{
    public function isCertified(string $userId): bool
    {
        return Organiser::query()->whereKey($userId)->whereNotNull('certified_at')->exists();
    }

    public function cards(array $userIds): array
    {
        $rows = Organiser::query()->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $cards = [];
        foreach ($userIds as $id) {
            $row = $rows->get($id);
            $cards[$id] = [
                'certified' => $row?->certified_at !== null,
                'record' => [
                    'tripsRun' => (int) ($row->trips_run ?? 0),
                    'campersJoined' => (int) ($row->campers_joined ?? 0),
                    'cancelledTrips' => (int) ($row->cancelled_trips ?? 0),
                    'cancelledCampers' => (int) ($row->cancelled_campers ?? 0),
                ],
            ];
        }

        return $cards;
    }

    public function publicPhone(string $userId): ?string
    {
        return Organiser::query()->whereKey($userId)->value('public_phone');
    }

    public function bump(string $userId, string $counter, int $by = 1): void
    {
        if (! in_array($counter, self::COUNTERS, true)) {
            throw new \InvalidArgumentException("Unknown counter {$counter}");
        }
        Organiser::query()->firstOrCreate(['user_id' => $userId]);
        Organiser::query()->whereKey($userId)->increment($counter, $by);
    }
}
