<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Modules\Identity\Models\User;
use Modules\Shared\Errors\ApiException;
use Modules\Verification\Jobs\EraseDiditSession;
use Modules\Verification\Models\VerificationSession;

beforeEach(fn () => config(['verification.didit' => [
    'base_url' => 'https://verification.didit.me', 'api_key' => 'didit-key', 'workflow_id' => 'wf', 'webhook_secret' => 'whsec',
]]));

it('queues an erase at Didit for every session when the account is deleted', function () {
    Queue::fake();
    $user = User::factory()->create();
    foreach (['sess-1', 'sess-2'] as $id) {
        (new VerificationSession)->forceFill(['session_id' => $id, 'user_id' => $user->id, 'photo_id' => '01J8Z3Q4X5Y6Z7A8B9C0D1E2F3'])->save();
    }

    $this->withToken(tokenFor($user))->deleteJson('/api/v1/me')->assertNoContent();

    Queue::assertPushed(EraseDiditSession::class, 2);
    Queue::assertPushed(EraseDiditSession::class, fn ($job) => $job->sessionId === 'sess-2');
    expect(VerificationSession::count())->toBe(0);
});

it('asks Didit for a privacy erasure', function () {
    Http::fake(['verification.didit.me/*' => Http::response(['session_id' => 'sess-1', 'face_retention_outcome' => 'deleted'])]);

    app()->call([new EraseDiditSession('sess-1'), 'handle']);

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'https://verification.didit.me/v3/session/sess-1/delete/'
        && $request->hasHeader('x-api-key', 'didit-key')
        && $request->data() === ['deletion_instruction' => 'privacy_erasure']);
});

it('treats an already deleted session as done', function () {
    Http::fake(['verification.didit.me/*' => Http::response(['detail' => 'Not found'], 404)]);
    app()->call([new EraseDiditSession('sess-1'), 'handle']);
    expect(true)->toBeTrue();
});

it('fails the job so the queue retries when Didit is down', function () {
    Http::fake(['verification.didit.me/*' => Http::response('oops', 503)]);
    expect(fn () => app()->call([new EraseDiditSession('sess-1'), 'handle']))->toThrow(ApiException::class);
    expect((new EraseDiditSession('sess-1'))->retryUntil()->greaterThan(now()->addDays(6)))->toBeTrue();
});

it('keeps retrying for a week and reports when it finally gives up', function () {
    $job = new EraseDiditSession('sess-1');
    expect($job->retryUntil()->greaterThan(now()->addDays(6)))->toBeTrue();

    Log::spy();
    $job->failed(new RuntimeException('down'));
    Log::shouldHaveReceived('error')->once();
});
