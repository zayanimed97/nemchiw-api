<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Chat\Events\MessagePosted;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Chat\Realtime\Realtime;
use Modules\Chat\Testing\FakeRealtime;
use Modules\Identity\Models\User;
use Modules\Parties\Models\Party;
use Modules\Trips\Contracts\TripRequests;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Tests\Factories\TripFactory;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

/** A ready party (leader Hamza + Amine + Rim) that asked for a trip: its conversation exists. */
function askedParty($test): array
{
    $leader = tripReady(['first_name' => 'Hamza']);
    $amine = tripReady(['first_name' => 'Amine']);
    $rim = tripReady(['first_name' => 'Rim']);
    $party = makeParty($test, $leader);
    joinParty($test, $party, $amine);
    joinParty($test, $party, $rim);
    $trip = TripFactory::new()->create(['price_tnd' => 50]);
    partyAs($test, $leader)->postJson("/api/v1/trips/{$trip->id}/requests", ['partyId' => $party->id])->assertCreated();
    $request = TripRequest::query()->sole();
    $conversation = Conversation::query()->where('request_id', $request->id)->sole();

    return [$conversation, $leader, $amine, $rim, User::find($trip->organiser_id), $party];
}

function say($test, User $as, Conversation $c, string $body = 'Salam')
{
    return $test->withToken(tokenFor($as))->postJson("/api/v1/conversations/{$c->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => $body,
    ]);
}

it('lists every other member as a reader and reads for them', function () {
    [$c, $leader, $amine, $rim] = askedParty($this);

    expect(DB::table('conversation_readers')->where('conversation_id', $c->id)->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$amine, $rim])->pluck('id')->sort()->values()->all());
    expect($c->camper_id)->toBe($leader->id);

    $this->withToken(tokenFor($amine))->getJson("/api/v1/conversations/{$c->id}/messages")->assertOk();
    $feed = $this->withToken(tokenFor($amine))->getJson('/api/v1/me/conversations')->assertOk();
    $feed->assertJsonPath('data.0.id', $c->id)->assertJsonPath('data.0.role', 'member')
        ->assertJsonPath('data.0.party.leaderName', 'Hamza')->assertJsonPath('data.0.party.organiserName', 'Momen');
});

it('names the sender in party conversations only', function () {
    [$c, $leader, $amine] = askedParty($this);
    say($this, $leader, $c, 'Hi all')->assertCreated()->assertJsonPath('senderName', 'Hamza');

    $rows = $this->withToken(tokenFor($amine))->getJson("/api/v1/conversations/{$c->id}/messages")->json('data');
    expect(collect($rows)->firstWhere('body', 'Hi all')['senderName'])->toBe('Hamza');

    [$solo, $camper] = askToJoin($this);
    say($this, $camper, $solo)->assertCreated()->assertJsonPath('senderName', null);
    $this->withToken(tokenFor($camper))->getJson('/api/v1/me/conversations')->assertJsonPath('data.0.party', null);
});

it('refuses a reader writing', function () {
    [$c, , $amine] = askedParty($this);

    say($this, $amine, $c)->assertStatus(403)->assertJsonPath('code', 'forbidden');
});

it('lets the organiser write and tells every member but the sender', function () {
    [$c, $leader, $amine, $rim, $organiser] = askedParty($this);
    Event::fake([MessagePosted::class]);

    say($this, $organiser, $c)->assertCreated();
    Event::assertDispatched(MessagePosted::class, fn ($e) => collect($e->recipientIds)->sort()->values()->all()
        === collect([$leader, $amine, $rim])->pluck('id')->sort()->values()->all());

    say($this, $leader, $c)->assertCreated();
    Event::assertDispatched(MessagePosted::class, fn ($e) => $e->senderId === $leader->id
        && collect($e->recipientIds)->sort()->values()->all() === collect([$amine, $rim, $organiser])->pluck('id')->sort()->values()->all());
});

it('ends access when a member leaves', function () {
    [$c, , $amine] = askedParty($this);
    $party = TripRequest::query()->sole()->party_id;

    partyAs($this, $amine)->postJson("/api/v1/parties/{$party}/leave")->assertOk();

    $this->withToken(tokenFor($amine))->getJson("/api/v1/conversations/{$c->id}/messages")->assertStatus(403);
    $line = Message::query()->where('conversation_id', $c->id)->latest('id')->first();
    expect([$line->system_key, $line->system_params])->toBe(['party_member_left', ['name' => 'Amine']]);
    expect(Message::query()->where('system_key', 'camper')->exists())->toBeFalse();
});

it('writes party_member_removed with the removed person\'s name', function () {
    [$c, $leader, $amine] = askedParty($this);
    $party = TripRequest::query()->sole()->party_id;

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party}/members/".memberRow(Party::find($party), $amine)->id.'/remove')->assertOk();

    $line = Message::query()->where('conversation_id', $c->id)->latest('id')->first();
    expect([$line->system_key, $line->system_params])->toBe(['party_member_removed', ['name' => 'Amine']]);
    $this->withToken(tokenFor($amine))->getJson("/api/v1/conversations/{$c->id}/messages")->assertStatus(403);
});

it('moves the right to write when the leader leaves', function () {
    [$c, $leader, $amine, $rim] = askedParty($this);
    $party = TripRequest::query()->sole()->party_id;

    partyAs($this, $leader)->postJson("/api/v1/parties/{$party}/leave")->assertOk();

    $newLeaderId = TripRequest::query()->sole()->leader_id;
    expect($c->fresh()->camper_id)->toBe($newLeaderId);
    $newLeader = $newLeaderId === $amine->id ? $amine : $rim;
    $reader = $newLeaderId === $amine->id ? $rim : $amine;
    say($this, $newLeader, $c)->assertCreated();
    say($this, $reader, $c)->assertStatus(403);
    say($this, $leader, $c)->assertStatus(403);
    $this->withToken(tokenFor($leader))->getJson("/api/v1/conversations/{$c->id}/messages")->assertStatus(403);
    expect(DB::table('conversation_readers')->where('conversation_id', $c->id)->pluck('user_id')->all())->toBe([$reader->id]);
    $keys = Message::query()->where('conversation_id', $c->id)->where('system_key', 'party_leader_changed')->get();
    expect($keys)->toHaveCount(1);
    expect($keys[0]->system_params)->toBe(['from' => 'Hamza', 'to' => $newLeader->first_name]);
});

it('keeps the old leader as a reader when the lead changes and they stay', function () {
    [$c, $leader, $amine] = askedParty($this);
    $request = TripRequest::query()->sole();

    app(TripRequests::class)->changeLeader($request->id, $amine->id);

    expect($c->fresh()->camper_id)->toBe($amine->id);
    say($this, $leader, $c)->assertStatus(403);
    $this->withToken(tokenFor($leader))->getJson('/api/v1/me/conversations')->assertJsonPath('data.0.role', 'member');
    say($this, $amine, $c)->assertCreated();
});

it('signs the channel for readers and not for strangers', function () {
    [$c, , $amine] = askedParty($this);
    app()->instance(Realtime::class, new FakeRealtime);
    $body = ['socketId' => '1234.5678', 'channelName' => "private-conversation.{$c->id}"];

    $this->withToken(tokenFor($amine))->postJson('/api/v1/broadcasting/auth', $body)->assertOk();
    $this->withToken(tokenFor(tripReady()))->postJson('/api/v1/broadcasting/auth', $body)->assertStatus(403);
});
