<?php

use Illuminate\Support\Str;

beforeEach(fn () => $this->travelTo('2026-10-10 10:00:00'));

it('lists my conversations with the other person, the last line and the unread count', function () {
    [$conversation, $camper, $organiser, $trip] = askToJoin($this, note: 'Salam!');

    $mine = $this->withToken(tokenFor($organiser))->getJson('/api/v1/me/conversations')->assertOk();

    $mine->assertJsonPath('full', true)
        ->assertJsonPath('data.0.id', $conversation->id)
        ->assertJsonPath('data.0.role', 'organiser')
        ->assertJsonPath('data.0.other.firstName', 'Amira')
        ->assertJsonPath('data.0.trip.title', $trip->title)
        ->assertJsonPath('data.0.requestStatus', 'pending')
        ->assertJsonPath('data.0.lastMessage.body', 'Salam!')
        ->assertJsonPath('data.0.unread', 2)
        ->assertJsonPath('data.0.closesAt', null);
    // The camper caused both lines (asked + their note): nothing unread for them.
    $this->withToken(tokenFor($camper))->getJson('/api/v1/me/conversations')
        ->assertJsonPath('data.0.role', 'camper')
        ->assertJsonPath('data.0.unread', 0);
    $this->withToken(tokenFor(tripReady()))->getJson('/api/v1/me/conversations')->assertJsonCount(0, 'data');
});

it('sends only what changed after the cursor', function () {
    [$conversation, $camper, $organiser] = askToJoin($this);
    $this->travel(5)->seconds(); // past the cursor's lag, which re-sends the last 2 seconds
    $first = $this->withToken(tokenFor($organiser))->getJson('/api/v1/me/conversations')->json('serverTime');

    $this->withToken(tokenFor($organiser))->getJson('/api/v1/me/conversations?updatedSince='.urlencode($first))
        ->assertJsonPath('full', false)->assertJsonCount(0, 'data');

    $this->withToken(tokenFor($camper))->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => 'Is there water?',
    ])->assertCreated();
    $this->withToken(tokenFor($organiser))->getJson('/api/v1/me/conversations?updatedSince='.urlencode($first))
        ->assertJsonPath('full', false)->assertJsonPath('data.0.lastMessage.body', 'Is there water?');
});
