<?php

use Modules\Chat\Models\Message;
use Modules\Identity\Models\User;
use Modules\Trips\Actions\Tick;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Tests\Factories\TripFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

it('opens a conversation when someone asks, with the note as the first message', function () {
    [$conversation, $camper, $organiser, $trip] = askToJoin($this, note: 'I have my own tent.');

    expect($conversation->organiser_id)->toBe($organiser->id);
    expect($conversation->trip_title)->toBe($trip->title);
    expect($conversation->closes_at)->toBeNull();
    $messages = Message::query()->where('conversation_id', $conversation->id)->orderBy('id')->get();
    expect($messages->map(fn ($m) => [$m->kind, $m->system_key, $m->body, $m->sender_id])->all())->toBe([
        ['system', 'asked', null, null],
        ['text', null, 'I have my own tent.', $camper->id],
    ]);
    expect($messages[0]->actor_id)->toBe($camper->id);
    expect($messages[0]->system_params)->toBe(['name' => 'Amira']);
});

it('writes a system line for each change, naming who made it', function () {
    [$conversation, $camper, $organiser] = askToJoin($this);
    $request = TripRequest::query()->findOrFail($conversation->request_id);

    $this->withToken(tokenFor($organiser))->postJson("/api/v1/requests/{$request->id}/accept")->assertOk();

    $line = Message::query()->where('conversation_id', $conversation->id)->orderByDesc('id')->first();
    expect([$line->system_key, $line->system_params, $line->actor_id])->toBe(['accepted', ['name' => 'Momen'], $organiser->id]);
    expect($conversation->fresh()->closes_at)->toBeNull();
});

it('closes the conversation when the request ends', function (string $action, string $as, string $key) {
    [$conversation, $camper, $organiser] = askToJoin($this);
    $actor = $as === 'camper' ? $camper : $organiser;

    $this->withToken(tokenFor($actor))->postJson("/api/v1/requests/{$conversation->request_id}/{$action}")->assertOk();

    expect($conversation->fresh()->closes_at?->equalTo(now()))->toBeTrue();
    expect(Message::query()->where('conversation_id', $conversation->id)->orderByDesc('id')->value('system_key'))->toBe($key);
})->with([
    'declined' => ['decline', 'organiser', 'declined'],
    'withdrawn' => ['withdraw', 'camper', 'withdrawn'],
]);

it('tells every open conversation of a cancelled trip', function () {
    $trip = TripFactory::new()->create();
    [$first] = askToJoin($this, $trip);
    [$second] = askToJoin($this, $trip, tripReady(['first_name' => 'Nour']));

    $this->withToken(tokenFor(User::find($trip->organiser_id)))->postJson("/api/v1/trips/{$trip->id}/cancel")->assertOk();

    foreach ([$first, $second] as $c) {
        expect(Message::query()->where('conversation_id', $c->id)->orderByDesc('id')->value('system_key'))->toBe('trip_cancelled');
        expect($c->fresh()->closes_at)->not->toBeNull();
    }
});

it('notes a meetup change in open conversations only', function () {
    $trip = TripFactory::new()->create();
    [$open] = askToJoin($this, $trip);
    [$closed, $nour] = askToJoin($this, $trip, tripReady(['first_name' => 'Nour']));
    $this->withToken(tokenFor($nour))->postJson("/api/v1/requests/{$closed->request_id}/withdraw")->assertOk();

    $earlier = $trip->meetup_at->copy()->subHour(); // copy(): Eloquent dates are mutable

    $this->withToken(tokenFor(User::find($trip->organiser_id)))
        ->postJson("/api/v1/trips/{$trip->id}", ['meetupAt' => $earlier->toIso8601String()])->assertOk();

    $last = fn ($c) => Message::query()->where('conversation_id', $c->id)->orderByDesc('id')->first();
    expect($last($open)->system_key)->toBe('trip_edited');
    expect($last($open)->system_params)->toBe(['meetupAt' => $earlier->utc()->format('Y-m-d\TH:i:s.v\Z')]);
    expect($last($closed)->system_key)->toBe('withdrawn');
});

it('keeps a joined conversation open for 7 days after the trip', function () {
    $trip = TripFactory::new()->create(['starts_at' => '2026-10-11 09:00', 'ends_at' => '2026-10-12 09:00', 'meetup_at' => '2026-10-11 08:00']);
    [$conversation] = askToJoin($this, $trip);
    TripRequest::query()->whereKey($conversation->request_id)->update(['status' => 'joined', 'joined_at' => now()]);

    $this->travelTo('2026-10-12 10:00:00');
    app(Tick::class)();

    expect($conversation->fresh()->closes_at->toDateTimeString())->toBe('2026-10-19 09:00:00');
});
