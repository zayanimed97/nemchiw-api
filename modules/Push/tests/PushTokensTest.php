<?php

use Modules\Push\Models\PushToken;

const TOKEN = 'ExponentPushToken[abcdefghijklmnop]';

it('registers, moves to the latest person, and forgets a token', function () {
    $amira = tripReady();
    $sami = tripReady();

    $this->withToken(tokenFor($amira))->postJson('/api/v1/me/push-token', ['token' => TOKEN, 'platform' => 'ios', 'locale' => 'ar'])->assertNoContent();
    expect(PushToken::query()->where('token', TOKEN)->value('user_id'))->toBe($amira->id);

    // Same phone, another account signs in.
    $this->withToken(tokenFor($sami))->postJson('/api/v1/me/push-token', ['token' => TOKEN, 'platform' => 'ios', 'locale' => 'fr'])->assertNoContent();
    expect(PushToken::query()->where('token', TOKEN)->first()->only('user_id', 'locale'))->toBe(['user_id' => $sami->id, 'locale' => 'fr']);

    $this->withToken(tokenFor($amira))->postJson('/api/v1/me/push-token/delete', ['token' => TOKEN])->assertNoContent();
    expect(PushToken::query()->count())->toBe(1); // not hers any more
    $this->withToken(tokenFor($sami))->postJson('/api/v1/me/push-token/delete', ['token' => TOKEN])->assertNoContent();
    expect(PushToken::query()->count())->toBe(0);
});

it('refuses what is not an Expo token', function () {
    $this->withToken(tokenFor(tripReady()))->postJson('/api/v1/me/push-token', ['token' => 'abc', 'platform' => 'ios', 'locale' => 'en'])
        ->assertStatus(422);
});
