<?php

namespace Modules\Parties\Actions;

use Modules\Identity\Contracts\People;
use Modules\Parties\Models\Party;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Shared\Support\ContactHash;

/** "Is this phone or email on Nemchiw?" Answers the public card or not_found, never the contact. */
final class LookupContact
{
    public function __construct(private readonly People $people, private readonly LookupLimiter $limiter) {}

    /** @return array{person: array<string, mixed>, blocked: 'self'|'member'|null} */
    public function handle(Party $party, string $callerId, string $contact): array
    {
        $this->limiter->hit($callerId);

        $normalised = ContactHash::normalise($contact);
        $found = $normalised === null ? null : $this->people->findByContact($normalised);
        $person = $found === null ? null : ($this->people->publicProfiles([$found])[$found] ?? null);
        if ($person === null) {
            throw new ApiException(ApiErrorCode::NotFound, 'Not found');
        }

        $blocked = null;
        if ($found === $callerId) {
            $blocked = 'self';
        } elseif (PartyMember::query()->where('party_id', $party->id)->where('user_id', $found)
            ->whereIn('state', [PartyMember::INVITED, PartyMember::IN])->exists()) {
            $blocked = 'member';
        }

        return ['person' => $person, 'blocked' => $blocked];
    }
}
