<?php

use Modules\Chat\Models\Conversation;
use Modules\Identity\Models\User;
use Modules\Trips\Models\Trip;
use Modules\Trips\Tests\Factories\TripFactory;

/** A camper asks through the API, which opens the conversation. */
function askToJoin($test, ?Trip $trip = null, ?User $camper = null, ?string $note = null): array
{
    $trip ??= TripFactory::new()->create();
    $camper ??= tripReady(['first_name' => 'Amira']);
    $test->withToken(tokenFor($camper))->postJson("/api/v1/trips/{$trip->id}/requests", ['note' => $note])->assertCreated();
    $conversation = Conversation::query()->where('trip_id', $trip->id)->where('camper_id', $camper->id)->latest('id')->firstOrFail();

    return [$conversation, $camper, User::find($trip->organiser_id), $trip];
}
