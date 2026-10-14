<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Chat\Contracts\ConversationDirectory;
use Modules\Chat\Models\Message;
use Modules\Media\Testing\Images;

beforeEach(function () {
    $this->travelTo('2026-10-10 10:00:00');
    Storage::fake('local');
});

function send($test, $conversation, $user, array $body)
{
    return $test->withToken(tokenFor($user))->postJson("/api/v1/conversations/{$conversation->id}/messages", $body);
}

it('stores a text once per client id', function () {
    [$conversation, $camper] = askToJoin($this);
    $clientId = (string) Str::uuid();

    $first = send($this, $conversation, $camper, ['clientId' => $clientId, 'kind' => 'text', 'body' => 'Salam'])
        ->assertCreated()
        ->assertJsonPath('kind', 'text')
        ->assertJsonPath('body', 'Salam')
        ->assertJsonPath('clientId', $clientId)
        ->assertJsonPath('senderId', $camper->id);
    send($this, $conversation, $camper, ['clientId' => $clientId, 'kind' => 'text', 'body' => 'Salam'])
        ->assertOk()
        ->assertJsonPath('id', $first->json('id'));

    expect(Message::query()->where('client_id', $clientId)->count())->toBe(1);
});

it('stores a location', function () {
    [$conversation, , $organiser] = askToJoin($this);

    send($this, $conversation, $organiser, ['clientId' => (string) Str::uuid(), 'kind' => 'location', 'lat' => 36.4, 'lng' => 10.14, 'accuracyM' => 15])
        ->assertCreated()
        ->assertJsonPath('location', ['lat' => 36.4, 'lng' => 10.14, 'accuracyM' => 15]);
});

it('refuses empty, too long, and invalid bodies', function () {
    [$conversation, $camper] = askToJoin($this);

    send($this, $conversation, $camper, ['clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => '   '])
        ->assertStatus(422)->assertJsonPath('code', 'validation');
    send($this, $conversation, $camper, ['clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => str_repeat('a', 1001)])
        ->assertStatus(422)->assertJsonPath('code', 'message_too_long');
    send($this, $conversation, $camper, ['clientId' => 'not-a-uuid', 'kind' => 'text', 'body' => 'hi'])
        ->assertStatus(422);
    send($this, $conversation, $camper, ['clientId' => (string) Str::uuid(), 'kind' => 'location', 'lat' => 200, 'lng' => 10])
        ->assertStatus(422);
});

it('keeps conversations to their two people', function () {
    [$conversation] = askToJoin($this);
    $stranger = tripReady();

    send($this, $conversation, $stranger, ['clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => 'hi'])
        ->assertStatus(403)->assertJsonPath('code', 'forbidden');
    $this->withToken(tokenFor($stranger))->getJson("/api/v1/conversations/{$conversation->id}/messages")->assertStatus(403);
    $this->withToken(tokenFor($stranger))->postJson("/api/v1/conversations/{$conversation->id}/read", ['viewing' => true])->assertStatus(403);
    $this->withToken(tokenFor($stranger))->getJson('/api/v1/conversations/01jzzzzzzzzzzzzzzzzzzzzzzz/messages')->assertStatus(404);
});

it('refuses new messages once closed, but answers a repeat of an old one', function () {
    [$conversation, $camper] = askToJoin($this);
    $clientId = (string) Str::uuid();
    send($this, $conversation, $camper, ['clientId' => $clientId, 'kind' => 'text', 'body' => 'one'])->assertCreated();
    $this->withToken(tokenFor($camper))->postJson("/api/v1/requests/{$conversation->request_id}/withdraw")->assertOk();

    send($this, $conversation, $camper, ['clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => 'two'])
        ->assertStatus(409)->assertJsonPath('code', 'conversation_closed');
    send($this, $conversation, $camper, ['clientId' => $clientId, 'kind' => 'text', 'body' => 'one'])->assertOk();
});

it('takes a photo, re-encodes it, and serves it to the two people only', function () {
    [$conversation, $camper, $organiser] = askToJoin($this);

    $sent = $this->withToken(tokenFor($camper))->post("/api/v1/conversations/{$conversation->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'photo', 'body' => 'My shoes',
        'photo' => UploadedFile::fake()->createWithContent('shoes.jpg', Images::jpegWithExif()),
    ], ['Accept' => 'application/json'])->assertCreated();

    $hash = $sent->json('photo.hash');
    expect($hash)->toMatch('/^[0-9a-f]{32}$/');
    expect($sent->json('body'))->toBe('My shoes');
    $url = "/api/v1/conversations/{$conversation->id}/photos/{$hash}-480.webp";
    $this->withToken(tokenFor($organiser))->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->withToken(tokenFor(tripReady()))->get($url, ['Accept' => 'application/json'])->assertStatus(403);
    $this->withToken(tokenFor($organiser))->get("/api/v1/conversations/{$conversation->id}/photos/".str_repeat('a', 32).'-480.webp', ['Accept' => 'application/json'])
        ->assertStatus(404);
});

it('names an unreadable photo', function () {
    [$conversation, $camper] = askToJoin($this);

    $this->withToken(tokenFor($camper))->post("/api/v1/conversations/{$conversation->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'photo',
        'photo' => UploadedFile::fake()->createWithContent('x.gif', Images::gif()),
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('code', 'photo_invalid');
});

it('pages messages oldest first', function () {
    config(['chat.messages_per_minute' => 1000]);
    [$conversation, $camper] = askToJoin($this); // 1 system line
    foreach (range(1, 59) as $n) {
        send($this, $conversation, $camper, ['clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => "m{$n}"])->assertCreated();
    }
    $base = "/api/v1/conversations/{$conversation->id}/messages";

    $latest = $this->withToken(tokenFor($camper))->getJson($base)->assertJsonPath('hasMore', true)->assertJsonCount(50, 'data');
    expect($latest->json('data.0.body'))->toBe('m10');
    expect($latest->json('data.49.body'))->toBe('m59');

    $older = $this->withToken(tokenFor($camper))->getJson($base.'?before='.$latest->json('data.0.id'))
        ->assertJsonPath('hasMore', false)->assertJsonCount(10, 'data');
    expect($older->json('data.0.system.key'))->toBe('asked');

    $this->withToken(tokenFor($camper))->getJson($base.'?after='.$latest->json('data.48.id'))
        ->assertJsonPath('hasMore', false)->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'm59');
});

it('marks read, and says while someone is looking', function () {
    [$conversation, $camper, $organiser] = askToJoin($this, note: 'Salam');
    $last = Message::query()->where('conversation_id', $conversation->id)->max('id');

    $this->withToken(tokenFor($organiser))->postJson("/api/v1/conversations/{$conversation->id}/read", ['lastReadId' => $last, 'viewing' => true])
        ->assertOk()->assertJsonPath('unread', 0);
    expect(app(ConversationDirectory::class)->isViewing($conversation->id, $organiser->id))->toBeTrue();

    $this->travel(91)->seconds();
    expect(app(ConversationDirectory::class)->isViewing($conversation->id, $organiser->id))->toBeFalse();

    // An older id never moves the mark back.
    $first = Message::query()->where('conversation_id', $conversation->id)->min('id');
    $this->withToken(tokenFor($organiser))->postJson("/api/v1/conversations/{$conversation->id}/read", ['lastReadId' => $first, 'viewing' => false])
        ->assertJsonPath('unread', 0);
});

it('limits how fast one person writes', function () {
    config(['chat.messages_per_minute' => 3]);
    [$conversation, $camper] = askToJoin($this);

    foreach (range(1, 3) as $n) {
        send($this, $conversation, $camper, ['clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => "m{$n}"])->assertCreated();
    }
    send($this, $conversation, $camper, ['clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => 'm4'])
        ->assertStatus(429)->assertJsonPath('code', 'rate_limited');
});
