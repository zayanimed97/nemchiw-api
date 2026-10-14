<?php

use Illuminate\Support\Str;
use Modules\Chat\Realtime\NullRealtime;
use Modules\Chat\Realtime\PusherRealtime;
use Modules\Chat\Realtime\Realtime;
use Modules\Chat\Testing\FakeRealtime;
use Pusher\Pusher;

beforeEach(function () {
    $this->travelTo('2026-10-10 10:00:00');
    $this->withoutDefer();
    FakeRealtime::reset();
    app()->instance(Realtime::class, new FakeRealtime);
});

it('publishes each stored message to its conversation channel', function () {
    [$conversation, $camper] = askToJoin($this);
    FakeRealtime::reset();

    $sent = $this->withToken(tokenFor($camper))->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => 'Salam',
    ])->assertCreated();

    expect(FakeRealtime::$published)->toHaveCount(1);
    [$channel, $event, $payload] = FakeRealtime::$published[0];
    expect($channel)->toBe("private-conversation.{$conversation->id}");
    expect($event)->toBe('message.created');
    expect($payload['id'])->toBe($sent->json('id'));
});

it('still stores the message when publishing fails', function () {
    [$conversation, $camper] = askToJoin($this);
    FakeRealtime::$fail = true;

    $this->withToken(tokenFor($camper))->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => 'Salam',
    ])->assertCreated();
});

it('signs the channel for its two people only', function () {
    [$conversation, $camper] = askToJoin($this);
    $body = ['socketId' => '1234.5678', 'channelName' => "private-conversation.{$conversation->id}"];

    $this->withToken(tokenFor($camper))->postJson('/api/v1/broadcasting/auth', $body)
        ->assertOk()->assertJsonPath('auth', "fake:private-conversation.{$conversation->id}:1234.5678");
    $this->withToken(tokenFor(tripReady()))->postJson('/api/v1/broadcasting/auth', $body)->assertStatus(403);
    $this->withToken(tokenFor($camper))->postJson('/api/v1/broadcasting/auth', ['socketId' => '1234.5678', 'channelName' => 'private-other'])
        ->assertStatus(422);
});

it('signs like Pusher does', function () {
    $realtime = new PusherRealtime(new Pusher('appkey', 'secret', '123', ['host' => 'main.pusher.ably.net', 'useTLS' => true]));

    expect($realtime->authorize('private-conversation.abc', '1234.5678'))
        ->toBe(['auth' => 'appkey:'.hash_hmac('sha256', '1234.5678:private-conversation.abc', 'secret')]);
});

it('turns realtime on only when keys are set', function () {
    app()->forgetInstance(Realtime::class);
    config(['chat.realtime.key' => null]);
    expect(app(Realtime::class))->toBeInstanceOf(NullRealtime::class);

    app()->forgetInstance(Realtime::class);
    config(['chat.realtime' => ['app_id' => 'app', 'key' => 'app.key', 'secret' => 's', 'host' => 'main.pusher.ably.net']]);
    expect(app(Realtime::class))->toBeInstanceOf(PusherRealtime::class);
});
