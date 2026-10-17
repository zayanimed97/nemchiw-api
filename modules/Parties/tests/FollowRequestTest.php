<?php

use Illuminate\Support\Facades\DB;
use Modules\Parties\Models\Party;
use Modules\Shared\Events\RequestChanged;
use Modules\Shared\Events\TripFinished;
use Modules\Trips\Actions\Tick;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

it('makes the pairs friends with the trip place once they have joined together', function () {
    [$leader, $party, $members, $trip] = readyPartyOf($this, 2);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    $request = TripRequest::query()->sole();
    expect(DB::table('friendships')->count())->toBe(4); // leader-member pairs from the invitations
    acceptAsOrganiser($this, $request);

    scan($this, $request, codeFor($request))->assertOk();

    $all = DB::table('friendships')->get();
    expect($all)->toHaveCount(6);
    foreach ($all as $row) {
        expect(json_decode($row->reason, true))->toBe(['kind' => 'trip', 'place' => 'Ain Draham']);
    }
});

it('ignores requests of people with no party', function () {
    $request = TripRequestFactory::new()->accepted()->create();

    scan($this, $request, codeFor($request))->assertOk();

    expect(Party::query()->count())->toBe(0);
});

it('moves a going party to over when the trip finishes', function () {
    [$leader, $party, , $trip] = readyPartyOf($this, 1);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    $request = TripRequest::query()->sole();
    acceptAsOrganiser($this, $request);
    scan($this, $request, codeFor($request))->assertOk();

    event(new TripFinished($trip->id));

    expect($party->fresh()->state)->toBe('over')->and($request->fresh()->status)->toBe('joined');
});

it('does not revive a party that already ended when its request is withdrawn', function () {
    [$leader, $party, , $trip] = readyPartyOf($this, 1);
    askAsParty($this, $leader, $party, $trip)->assertCreated();

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/end")->assertOk();

    expect($party->fresh()->state)->toBe('over');
});

it('lapsed requests return the party to forming', function () {
    [$leader, $party, , $trip] = readyPartyOf($this, 1);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    $this->travelTo('2026-10-20 10:00:00');

    app(Tick::class)();

    expect(TripRequest::query()->sole()->status)->toBe('lapsed')->and($party->fresh()->state)->toBe('forming');
});

it('a late accepted for an ended request does not move the party', function () {
    [$leader, $party, , $trip] = readyPartyOf($this, 1);
    askAsParty($this, $leader, $party, $trip)->assertCreated();
    $request = TripRequest::query()->sole();
    partyAs($this, $leader)->postJson("/api/v1/requests/{$request->id}/withdraw")->assertOk();
    expect($party->fresh()->state)->toBe('forming');

    event(new RequestChanged($request->id, $trip->id, $leader->id, $trip->organiser_id, 'accepted', 'organiser'));
    event(new RequestChanged($request->id, $trip->id, $leader->id, $trip->organiser_id, 'scanned', 'camper'));

    $party->refresh();
    expect($party->state)->toBe('forming')->and($party->request_id)->toBeNull();
});
