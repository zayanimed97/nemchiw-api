<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Models\User;
use Modules\Media\Contracts\Photos;
use Modules\Media\Testing\Images;
use Modules\Verification\Contracts\IdentityVerifier;
use Modules\Verification\Didit\DiditVerifier;
use Modules\Verification\Fake\FakeVerifier;

beforeEach(function () {
    Storage::fake('photos');
    Http::preventStrayRequests();
    config(['verification.didit.api_key' => '', 'verification.didit.workflow_id' => '']);
    app()->detectEnvironment(fn () => 'local');
    $this->user = User::factory()->create();
    $this->token = tokenFor($this->user);
    $this->withToken($this->token)->post('/api/v1/me/photo', ['photo' => Images::upload(Images::jpeg())], ['Accept' => 'application/json'])->assertOk();
    app('auth')->forgetGuards();
});

it('uses the fake only locally and without a Didit key', function () {
    expect(app(IdentityVerifier::class))->toBeInstanceOf(FakeVerifier::class);

    config(['verification.didit.api_key' => 'didit-key']);
    expect(app(IdentityVerifier::class))->toBeInstanceOf(DiditVerifier::class);

    config(['verification.didit.api_key' => '']);
    app()->detectEnvironment(fn () => 'production');
    expect(app(IdentityVerifier::class))->toBeInstanceOf(DiditVerifier::class);
});

it('returns a mock session (the app skips the Didit screens) and verifies the photo', function () {
    $this->withToken($this->token)->postJson('/api/v1/me/verification')
        ->assertOk()->assertJsonPath('sessionToken', fn (string $token) => str_starts_with($token, 'mock-'));

    expect(app(Photos::class)->current($this->user->id)['status'])->toBe('verified');
});

it('rejects the photo when told to', function () {
    config(['verification.fake_outcome' => 'rejected']);

    $this->withToken($this->token)->postJson('/api/v1/me/verification')->assertOk();

    expect(app(Photos::class)->current($this->user->id)['status'])->toBe('rejected');
});
