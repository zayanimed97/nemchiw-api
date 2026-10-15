<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Media\Testing\Images;

beforeEach(function () {
    $this->travelTo('2026-10-10 10:00:00');
    Storage::fake('local');
});

it('keeps the conversation for the other side when someone deletes their account', function () {
    [$conversation, $camper, $organiser] = askToJoin($this, note: 'Salam');
    $photo = $this->withToken(tokenFor($camper))->post("/api/v1/conversations/{$conversation->id}/messages", [
        'clientId' => (string) Str::uuid(), 'kind' => 'photo',
        'photo' => UploadedFile::fake()->createWithContent('a.jpg', Images::jpeg()),
    ], ['Accept' => 'application/json'])->assertCreated()->json('photo.hash');

    $this->withToken(tokenFor($camper))->postJson('/api/v1/me/delete')->assertNoContent();

    $feed = $this->withToken(tokenFor($organiser))->getJson('/api/v1/me/conversations')->assertOk();
    $feed->assertJsonPath('data.0.id', $conversation->id)
        ->assertJsonPath('data.0.other', null)
        ->assertJsonPath('data.0.requestStatus', null);
    expect($feed->json('data.0.closesAt'))->not->toBeNull();

    $messages = $this->withToken(tokenFor($organiser))->getJson("/api/v1/conversations/{$conversation->id}/messages")->json('data');
    $note = collect($messages)->firstWhere('body', 'Salam');
    expect($note['senderId'])->toBeNull();
    expect(collect($messages)->firstWhere('kind', 'photo')['photo'])->toBeNull();
    Storage::disk('local')->assertMissing("chat/{$conversation->id}/{$photo}-480.webp");
});
