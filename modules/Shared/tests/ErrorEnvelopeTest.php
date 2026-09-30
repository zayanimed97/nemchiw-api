<?php

use Illuminate\Support\Facades\Route;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

beforeEach(function () {
    Route::prefix('api/v1/test')->middleware('api')->group(function () {
        Route::get('taken', fn () => throw new ApiException(ApiErrorCode::PhoneTaken, 'Phone in use'));
        Route::get('slow-down', fn () => throw new ApiException(ApiErrorCode::RateLimited, 'Wait', 42));
        Route::post('validate', fn () => request()->validate(['name' => ['required']]));
        Route::get('boom', fn () => throw new RuntimeException('db password is hunter2'));
        Route::get('private', fn () => 'ok')->middleware('auth:sanctum');
        Route::post('echo', fn () => ['ok' => true]);
    });
});

it('renders ApiException as the envelope with its status', function () {
    $this->getJson('/api/v1/test/taken')
        ->assertStatus(409)
        ->assertExactJson(['code' => 'phone_taken', 'message' => 'Phone in use']);
});

it('adds retryAfter and the Retry-After header for rate limits', function () {
    $this->getJson('/api/v1/test/slow-down')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '42')
        ->assertJson(['code' => 'rate_limited', 'retryAfter' => 42]);
});

it('maps validation errors', function () {
    $this->postJson('/api/v1/test/validate', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation')
        ->assertJsonStructure(['message', 'errors' => ['name']]);
});

it('never leaks exception text, even in debug mode', function () {
    config(['app.debug' => true]);
    $response = $this->getJson('/api/v1/test/boom')->assertStatus(500);
    expect($response->json())->toBe(['code' => 'server', 'message' => 'Server error']);
    expect($response->getContent())->not->toContain('hunter2');
});

it('answers unknown routes with not_found', function () {
    $this->getJson('/api/v1/nope')->assertStatus(404)->assertJsonPath('code', 'not_found');
});

it('answers missing tokens with unauthenticated, not a redirect', function () {
    $this->get('/api/v1/test/private')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
});

it('sets security headers', function () {
    $this->postJson('/api/v1/test/echo')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('rejects bodies over 64 KB', function () {
    $this->postJson('/api/v1/test/echo', ['blob' => str_repeat('a', 70_000)])
        ->assertStatus(413)
        ->assertJsonPath('code', 'validation');
});

it('throttles the api group per client', function () {
    config(['shared.api_per_minute' => 120]);
    foreach (range(1, 120) as $_) {
        $this->postJson('/api/v1/test/echo')->assertOk();
    }
    $this->postJson('/api/v1/test/echo')->assertStatus(429)->assertJsonPath('code', 'rate_limited');
});
