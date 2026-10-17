<?php

namespace Modules\Parties\Services;

use Modules\Identity\Contracts\People;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Trips\Contracts\TripRequests;

/** Who in a party still needs something before it can ask for a given trip. */
final class Readiness
{
    public function __construct(private readonly People $people, private readonly TripRequests $trips) {}

    /**
     * @return array{ready: bool, size: int, freeSeats: int, blockers: list<array{memberId: ?string, userId: ?string, name: ?string, reason: string, missing: list<string>}>}
     */
    public function forTrip(Party $party, string $tripId): array
    {
        $trip = $this->trips->trip($tripId);
        if ($trip === null) {
            throw new ApiException(ApiErrorCode::NotFound, 'Not found');
        }

        $rows = PartyMember::query()->where('party_id', $party->id)->whereIn('state', [PartyMember::IN, PartyMember::INVITED])
            ->orderBy('invited_at')->orderBy('id')->get();

        $blockers = [];
        $size = 1;
        $leader = $this->personBlocker(null, $party->leader_id, $trip);
        if ($leader !== null) {
            $blockers[] = $leader;
        }
        foreach ($rows as $row) {
            $size++;
            if ($row->user_id === null) {
                $blockers[] = ['memberId' => $row->id, 'userId' => null, 'name' => null, 'reason' => 'not_on_app', 'missing' => []];
            } elseif ($row->state === PartyMember::INVITED) {
                $blockers[] = ['memberId' => $row->id, 'userId' => $row->user_id, 'name' => $this->firstName($row->user_id), 'reason' => 'no_answer', 'missing' => []];
            } elseif (($blocker = $this->personBlocker($row->id, $row->user_id, $trip)) !== null) {
                $blockers[] = $blocker;
            }
        }

        return [
            'ready' => $blockers === [],
            'size' => $size,
            'freeSeats' => $trip['seats'] - $trip['joined'],
            'blockers' => $blockers,
        ];
    }

    /** @param array{tripId: string, organiserId: string, womenOnly: bool} $trip */
    private function personBlocker(?string $memberId, string $userId, array $trip): ?array
    {
        $need = $this->people->readiness($userId);
        $reason = match (true) {
            $userId === $trip['organiserId'] => 'organiser',
            $this->trips->blockingRequestFor($trip['tripId'], $userId) !== null => 'already_requested',
            $need['tooYoung'] => 'too_young',
            $trip['womenOnly'] && $need['gender'] !== null && $need['gender'] !== 'female' => 'women_only',
            ! $need['ready'] => 'not_ready',
            default => null,
        };
        if ($reason === null) {
            return null;
        }

        return [
            'memberId' => $memberId, 'userId' => $userId, 'name' => $this->firstName($userId),
            'reason' => $reason, 'missing' => $reason === 'not_ready' ? $need['missing'] : [],
        ];
    }

    private function firstName(string $userId): ?string
    {
        $name = $this->people->publicProfiles([$userId])[$userId]['firstName'] ?? null;

        return $name === null || trim((string) $name) === '' ? null : (string) $name;
    }
}
