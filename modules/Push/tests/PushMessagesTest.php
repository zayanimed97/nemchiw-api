<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Media\Testing\Images;
use Modules\Push\Models\PushToken;

beforeEach(function () {
    $this->travelTo('2026-10-10 10:00:00');
    $this->withoutDefer();
});

/** Http::fake keeps the first matching fake, so each test sets Expo's answers once. */
function expoAnswers(array ...$answers): void
{
    $sequence = Http::fakeSequence('exp.host/*');
    foreach ($answers as $answer) {
        $sequence->push($answer['body'], $answer['status'] ?? 200);
    }
    $sequence->whenEmpty(Http::response(['data' => [['status' => 'ok', 'id' => 'r']]]));
}

function device($user, string $token, string $locale = 'en'): void
{
    PushToken::query()->create(['user_id' => $user->id, 'token' => $token, 'platform' => 'ios', 'locale' => $locale, 'last_seen_at' => now()]);
}

/** Every Expo message sent so far, flattened. */
function pushed(): array
{
    return Http::recorded()->flatMap(fn ($pair) => $pair[0]->data())->values()->all();
}

it('pushes a message to the other person, grouped by conversation', function () {
    expoAnswers();
    [$conversation, $camper, $organiser, $trip] = askToJoin($this);
    device($organiser, 'ExponentPushToken[org]');

    $this->withToken(tokenFor($camper))->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => 'Is there water?',
    ])->assertCreated();

    $message = collect(pushed())->firstWhere('body', 'Is there water?');
    expect($message)->toMatchArray([
        'to' => 'ExponentPushToken[org]',
        'title' => "Amira · {$trip->title}",
        'priority' => 'high',
        'threadId' => $conversation->id,
        'tag' => $conversation->id,
        'channelId' => 'default',
        'data' => ['type' => 'message', 'conversationId' => $conversation->id],
    ]);
});

it('stays quiet while the other person has the chat open', function () {
    expoAnswers();
    [$conversation, $camper, $organiser] = askToJoin($this);
    device($organiser, 'ExponentPushToken[org]');
    $post = fn (string $body) => $this->withToken(tokenFor($camper))->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => $body,
    ])->assertCreated();

    $this->withToken(tokenFor($organiser))->postJson("/api/v1/conversations/{$conversation->id}/read", ['viewing' => true])->assertOk();
    $post('while open');
    $this->withToken(tokenFor($organiser))->postJson("/api/v1/conversations/{$conversation->id}/read", ['viewing' => false])->assertOk();
    $post('after closing');

    $bodies = collect(pushed())->pluck('body')->all();
    expect($bodies)->not->toContain('while open');
    expect($bodies)->toContain('after closing');
});

it('writes the title in the phone\'s language and names photos', function () {
    expoAnswers();
    Storage::fake('local');
    [$conversation, $camper, $organiser, $trip] = askToJoin($this);
    device($organiser, 'ExponentPushToken[org]', 'ar');

    $this->withToken(tokenFor($camper))->post("/api/v1/conversations/{$conversation->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'photo',
        'photo' => UploadedFile::fake()->createWithContent('a.jpg', Images::jpeg()),
    ], ['Accept' => 'application/json'])->assertCreated();

    expect(collect(pushed())->last()['body'])->toBe('تصويرة');
});

it('forgets tokens Expo says are gone, and never fails the send', function () {
    expoAnswers(
        ['body' => ['data' => [['status' => 'error', 'message' => 'x', 'details' => ['error' => 'DeviceNotRegistered']]]]],
        ['body' => 'down', 'status' => 500],
    );
    [$conversation, $camper, $organiser] = askToJoin($this);
    device($organiser, 'ExponentPushToken[gone]');

    $this->withToken(tokenFor($camper))->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => 'hi',
    ])->assertCreated();
    expect(PushToken::query()->count())->toBe(0);

    device($organiser, 'ExponentPushToken[org]');
    $this->withToken(tokenFor($camper))->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'text', 'body' => 'still stored',
    ])->assertCreated();
});
