<?php

use Illuminate\Support\Facades\Event;
use Modules\Shared\Events\RequestChanged;
use Modules\Trips\Contracts\TripRequests;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Models\TripRequestMember;
use Modules\Trips\Tests\Factories\TripFactory;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

/** A request with a leader and two more members, built directly. */
function partyRequest(string $state = 'accepted'): array
{
    $trip = TripFactory::new()->create();
    $request = TripRequestFactory::new()->forTrip($trip)->{$state}()->create();
    $a = tripReady();
    $b = tripReady();
    foreach ([$a, $b] as $user) {
        TripRequestMember::query()->create(['request_id' => $request->id, 'user_id' => $user->id]);
    }

    return [$trip, $request, $a, $b];
}

it('finds the request that stops a person asking for a trip again', function () {
    [$trip, $request, $a] = partyRequest();
    $other = TripRequestFactory::new()->forTrip($trip)->create(['status' => 'declined']);
    $cancelled = TripRequestFactory::new()->forTrip($trip)->create(['status' => 'cancelled']);

    expect(app(TripRequests::class)->blockingRequestFor($trip->id, $a->id))->toBe($request->id);
    expect(app(TripRequests::class)->blockingRequestFor($trip->id, $request->leader_id))->toBe($request->id);
    expect(app(TripRequests::class)->blockingRequestFor($trip->id, $other->leader_id))->toBeNull();
    expect(app(TripRequests::class)->blockingRequestFor($trip->id, tripReady()->id))->toBeNull();
    expect(app(TripRequests::class)->blockingRequestFor($trip->id, $cancelled->leader_id))->toBe($cancelled->id);
});

it('removes a member who left and says so', function () {
    [$trip, $request, $a] = partyRequest();
    Event::fake([RequestChanged::class]);

    app(TripRequests::class)->removePartyMember($request->id, $a->id, $a->id);

    expect(TripRequestMember::query()->where('request_id', $request->id)->where('user_id', $a->id)->exists())->toBeFalse();
    expect($request->fresh()->status)->toBe('accepted');
    Event::assertDispatched(RequestChanged::class, fn ($e) => $e->change === 'member_left' && $e->note === $a->id && $e->requestId === $request->id);
});

it('says member_removed when someone else removes the member', function () {
    [$trip, $request, $a] = partyRequest('joined');
    Event::fake([RequestChanged::class]);

    app(TripRequests::class)->removePartyMember($request->id, $a->id, $request->leader_id);

    Event::assertDispatched(RequestChanged::class, fn ($e) => $e->change === 'member_removed' && $e->note === $a->id);
    expect($request->fresh()->status)->toBe('joined');
    expect(in_array('member_left', RequestChanged::ENDINGS, true))->toBeFalse();
});

it('withdraws the request when nobody is left', function () {
    $trip = TripFactory::new()->create();
    $request = TripRequestFactory::new()->forTrip($trip)->accepted()->create();
    Event::fake([RequestChanged::class]);

    app(TripRequests::class)->removePartyMember($request->id, $request->leader_id, $request->leader_id);

    expect($request->fresh()->status)->toBe('withdrawn')->and($request->fresh()->withdrawn_from)->toBe('accepted')
        ->and($request->fresh()->code_secret)->toBeNull();
    Event::assertDispatched(RequestChanged::class, fn ($e) => $e->change === 'withdrawn');
});

it('changes the leader', function () {
    [$trip, $request, $a] = partyRequest();
    Event::fake([RequestChanged::class]);

    app(TripRequests::class)->changeLeader($request->id, $a->id);

    expect($request->fresh()->leader_id)->toBe($a->id);
    Event::assertDispatched(RequestChanged::class, fn ($e) => $e->change === 'leader_changed' && $e->leaderId === $a->id && $e->note === $a->id);
});

it('withdraws a request for a party that ended, and leaves finished requests alone', function () {
    [$trip, $request] = partyRequest('joined');
    $declined = TripRequestFactory::new()->forTrip($trip)->create(['status' => 'declined']);
    Event::fake([RequestChanged::class]);

    app(TripRequests::class)->withdrawForParty($request->id);
    app(TripRequests::class)->withdrawForParty($declined->id);
    app(TripRequests::class)->removePartyMember($declined->id, $declined->leader_id, $declined->leader_id);

    expect($request->fresh()->status)->toBe('withdrawn')->and($request->fresh()->withdrawn_from)->toBe('joined');
    expect($declined->fresh()->status)->toBe('declined');
    Event::assertDispatchedTimes(RequestChanged::class, 1);
});

it('knows the party a request belongs to', function () {
    expect(TripRequest::query()->getModel()->getFillable())->toContain('party_id');
});
