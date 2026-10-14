<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Models\User;
use Modules\Shared\Events\RequestChanged;
use Modules\Shared\Events\TripEdited;
use Modules\Shared\Events\TripFinished;
use Modules\Shared\Events\TripReminderDue;
use Modules\Trips\Actions\RequestTransitions;
use Modules\Trips\Actions\Tick;
use Modules\Trips\Support\JoinCode;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

/** Events as they are really dispatched (after commit). Event::fake records too early for that. */
function heard(string $class): ArrayObject
{
    $seen = new ArrayObject;
    Event::listen($class, fn ($event) => $seen->append($event));

    return $seen;
}

it('tells the others when someone asks, with the note', function () {
    $seen = heard(RequestChanged::class);
    $trip = TripFactory::new()->create();
    $sami = tripReady();

    $this->withToken(tokenFor($sami))->postJson("/api/v1/trips/{$trip->id}/requests", ['note' => 'Salam'])->assertCreated();

    expect($seen)->toHaveCount(1);
    $e = $seen[0];
    expect([$e->change, $e->by, $e->leaderId, $e->organiserId, $e->tripId, $e->note])
        ->toBe(['asked', 'camper', $sami->id, $trip->organiser_id, $trip->id, 'Salam']);
});

it('names each change and who made it', function () {
    $seen = heard(RequestChanged::class);
    $request = TripRequestFactory::new()->create();
    $organiser = User::find($request->trip->organiser_id);

    $this->withToken(tokenFor($organiser))->postJson("/api/v1/requests/{$request->id}/accept")->assertOk();
    $this->withToken(tokenFor($organiser))->postJson("/api/v1/requests/{$request->id}/cancel")->assertOk();

    expect(collect($seen)->map(fn ($e) => "{$e->change}:{$e->by}")->all())
        ->toBe(['accepted:organiser', 'cancelled:organiser']);
});

it('reports withdrawals and scans as the camper', function () {
    $seen = heard(RequestChanged::class);
    $request = TripRequestFactory::new()->accepted()->create();
    $camper = User::find($request->leader_id);
    $code = JoinCode::at(base64_decode($request->fresh()->code_secret), JoinCode::counter(now()->getTimestamp()));

    $this->withToken(tokenFor($camper))->postJson("/api/v1/requests/{$request->id}/scan", ['code' => $code])->assertOk();
    $this->withToken(tokenFor($camper))->postJson("/api/v1/requests/{$request->id}/withdraw")->assertOk();

    expect(collect($seen)->map(fn ($e) => "{$e->change}:{$e->by}")->all())
        ->toBe(['scanned:camper', 'withdrawn:camper']);
});

it('marks requests ended by a trip cancellation', function () {
    $seen = heard(RequestChanged::class);
    $trip = TripFactory::new()->create();
    TripRequestFactory::new()->forTrip($trip)->create();
    TripRequestFactory::new()->forTrip($trip)->accepted()->create();

    $this->withToken(tokenFor(User::find($trip->organiser_id)))->postJson("/api/v1/trips/{$trip->id}/cancel")->assertOk();

    expect(collect($seen)->pluck('change')->all())->toBe(['trip_cancelled', 'trip_cancelled']);
    expect(collect($seen)->pluck('by')->unique()->all())->toBe(['organiser']);
});

it('tells nobody when the change rolls back', function () {
    $seen = heard(RequestChanged::class);
    $request = TripRequestFactory::new()->create();

    try {
        DB::transaction(function () use ($request) {
            app(RequestTransitions::class)->accept($request, $request->trip->organiser_id);
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect($seen)->toHaveCount(0);
    expect($request->fresh()->status)->toBe('pending');
});

it('lapses requests at the start as the system', function () {
    $seen = heard(RequestChanged::class);
    $trip = TripFactory::new()->create(['starts_at' => '2026-10-10 11:00', 'ends_at' => '2026-10-12 11:00']);
    TripRequestFactory::new()->forTrip($trip)->create();

    $this->travelTo('2026-10-10 12:00:00');
    app(Tick::class)();

    expect(collect($seen)->map(fn ($e) => "{$e->change}:{$e->by}")->all())->toBe(['lapsed:system']);
});

it('announces a meetup change, not other edits', function () {
    $seen = heard(TripEdited::class);
    $trip = TripFactory::new()->create();
    $momen = User::find($trip->organiser_id);

    $this->withToken(tokenFor($momen))->patchJson("/api/v1/trips/{$trip->id}", ['title' => 'New title'])->assertOk();
    $this->withToken(tokenFor($momen))->patchJson("/api/v1/trips/{$trip->id}", ['meetupAt' => $trip->meetup_at->copy()->subMinutes(30)->toIso8601String()])->assertOk();

    expect($seen)->toHaveCount(1);
    expect($seen[0]->fields)->toBe(['meetupAt']);
});

it('finishes trips and reminds once, a day ahead', function () {
    $finished = heard(TripFinished::class);
    $reminded = heard(TripReminderDue::class);
    $tomorrow = TripFactory::new()->create(['starts_at' => '2026-10-11 09:00', 'ends_at' => '2026-10-12 09:00', 'meetup_at' => '2026-10-11 08:30']);
    $later = TripFactory::new()->create(['starts_at' => '2026-10-14 09:00', 'ends_at' => '2026-10-15 09:00', 'meetup_at' => '2026-10-14 08:30']);
    $over = TripFactory::new()->create(['starts_at' => '2026-10-08 09:00', 'ends_at' => '2026-10-09 09:00', 'meetup_at' => '2026-10-08 08:30']);

    app(Tick::class)();
    app(Tick::class)();

    expect(collect($reminded)->pluck('tripId')->all())->toBe([$tomorrow->id]);
    expect(collect($finished)->pluck('tripId')->all())->toBe([$over->id]);
    expect($later->fresh()->reminded_at)->toBeNull();
});
