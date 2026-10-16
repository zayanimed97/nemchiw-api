<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Models\User;
use Modules\Parties\Models\PartyMember;
use Modules\Shared\Events\PartyChanged;

it('ends the party and withdraws an open request', function () {
    $fake = fakeTripRequests();
    $leader = User::factory()->create();
    $amine = User::factory()->create();
    $party = makeParty($this, $leader);
    joinParty($this, $party, $amine);
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/invites", ['contact' => '+21698765432'])->assertOk();
    $party->forceFill(['state' => 'asking', 'request_id' => '01k0000000000000000000req1'])->save();
    Event::fake([PartyChanged::class]);

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/end")->assertOk()->assertJsonPath('party', null);

    expect($party->fresh()->state)->toBe('over');
    expect($fake->calls)->toBe([['withdrawForParty', ['01k0000000000000000000req1']]]);
    expect(PartyMember::query()->where('party_id', $party->id)->whereNull('user_id')->count())->toBe(0);
    Event::assertDispatched(PartyChanged::class, fn ($e) => $e->change === 'ended' && $e->byId === $leader->id && $e->memberIds === [$leader->id, $amine->id]);
});

it('lets only the leader end it', function () {
    $leader = User::factory()->create();
    $amine = User::factory()->create();
    $party = makeParty($this, $leader);
    joinParty($this, $party, $amine);

    partyAs($this, $amine)->postJson("/api/v1/parties/{$party->id}/end")->assertForbidden()->assertJsonPath('code', 'not_leader');
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/end")->assertOk();
    partyAs($this, $leader)->postJson("/api/v1/parties/{$party->id}/end")->assertNotFound();
});

it('ends idle parties after 30 days', function () {
    $idle = makeParty($this, User::factory()->create());
    $asking = makeParty($this, User::factory()->create());
    $asking->forceFill(['state' => 'asking'])->save();
    $member = User::factory()->create();
    joinParty($this, $idle, $member);
    $fresh = makeParty($this, User::factory()->create());
    $this->travel(31)->days();
    $fresh->forceFill(['last_activity_at' => now()])->save();
    Event::fake([PartyChanged::class]);

    $this->artisan('parties:end-idle')->assertSuccessful();

    expect($idle->fresh()->state)->toBe('over')->and($asking->fresh()->state)->toBe('asking')->and($fresh->fresh()->state)->toBe('forming');
    Event::assertDispatched(PartyChanged::class, fn ($e) => $e->change === 'auto_ended' && $e->partyId === $idle->id
        && $e->byId === null && in_array($member->id, $e->memberIds, true));
    Event::assertDispatchedTimes(PartyChanged::class, 1);
});

it('schedules the idle sweep daily at 03:30 Tunis time', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'parties:end-idle'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('30 3 * * *')->and((string) $event->timezone)->toBe('Africa/Tunis');
});
