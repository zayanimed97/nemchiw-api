<?php

namespace Modules\Parties\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Parties\Models\Party;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class CreateParty
{
    public function handle(string $leaderId): Party
    {
        return DB::transaction(function () use ($leaderId) {
            Parties::lockPerson($leaderId);
            if (Parties::openFor($leaderId) !== null) {
                throw new ApiException(ApiErrorCode::AlreadyInParty, 'You are already in a party');
            }

            return Party::query()->create(['leader_id' => $leaderId, 'state' => Party::FORMING, 'last_activity_at' => now()]);
        });
    }
}
