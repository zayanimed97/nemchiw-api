<?php

use Modules\Identity\Models\User;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Trips\Actions\ResolveReport;
use Modules\Trips\Models\TripReport;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

it('lets the organiser report an unsure camper once', function () {
    $request = TripRequestFactory::new()->accepted()->create();
    $organiser = User::find($request->trip->organiser_id);

    $this->withToken(tokenFor($organiser))->postJson("/api/v1/requests/{$request->id}/reports", ['reason' => 'unpaid', 'note' => 'No answer for 3 days'])
        ->assertCreated()->assertJsonPath('status', 'open');
    $this->withToken(tokenFor($organiser))->postJson("/api/v1/requests/{$request->id}/reports", ['reason' => 'unpaid'])
        ->assertStatus(409)->assertJsonPath('code', 'already_requested');
    $this->withToken(tokenFor(tripReady()))->postJson("/api/v1/requests/{$request->id}/reports", ['reason' => 'other'])
        ->assertStatus(403);
    $this->withToken(tokenFor($organiser))->postJson("/api/v1/requests/{$request->id}/reports", ['reason' => 'gossip'])
        ->assertStatus(422);
});

it('closes an unpaid request with no penalty and shows the outcome to the reporter', function () {
    $request = TripRequestFactory::new()->accepted()->create();
    $organiserId = $request->trip->organiser_id;
    $this->withToken(tokenFor(User::find($organiserId)))->postJson("/api/v1/requests/{$request->id}/reports", ['reason' => 'unpaid']);

    app(ResolveReport::class)(TripReport::query()->sole(), 'closed_no_penalty', '01J0000000000000000000ADM1');

    expect($request->fresh())->status->toBe('closed')->ended_by->toBe('admin');
    expect(app(OrganiserRecords::class)->cards([$organiserId])[$organiserId]['record']['cancelledCampers'])->toBe(0);
    expect(TripReport::query()->sole())->status->toBe('closed_no_penalty')->resolved_by->toBe('01J0000000000000000000ADM1');
    $this->withToken(tokenFor(User::find($organiserId)))->getJson('/api/v1/me/trips')
        ->assertJsonPath('hosting.0.requests', []); // closed requests leave the hosting list
});

it('can close a report as an organiser cancellation', function () {
    $request = TripRequestFactory::new()->joined()->create();
    $this->withToken(tokenFor(User::find($request->leader_id)))->postJson("/api/v1/requests/{$request->id}/reports", ['reason' => 'behaviour']);

    app(ResolveReport::class)(TripReport::query()->sole(), 'closed_organiser', '01J0000000000000000000ADM1');

    $organiserId = $request->trip->organiser_id;
    expect($request->fresh()->status)->toBe('cancelled');
    expect(app(OrganiserRecords::class)->cards([$organiserId])[$organiserId]['record']['cancelledCampers'])->toBe(1);
    $this->withToken(tokenFor(User::find($request->leader_id)))->getJson("/api/v1/trips/{$request->trip_id}")
        ->assertJsonPath('myRequest.report.status', 'closed_organiser');
});

it('dismisses without changing anything, and refuses to resolve twice', function () {
    $request = TripRequestFactory::new()->accepted()->create();
    $this->withToken(tokenFor(User::find($request->trip->organiser_id)))->postJson("/api/v1/requests/{$request->id}/reports", ['reason' => 'other']);
    $report = TripReport::query()->sole();

    app(ResolveReport::class)($report, 'dismissed', '01J0000000000000000000ADM1');
    expect($request->fresh()->status)->toBe('accepted');

    app(ResolveReport::class)($report->fresh(), 'closed_no_penalty', '01J0000000000000000000ADM1');
})->throws(InvalidArgumentException::class);
