<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Identity\Models\User;
use Modules\Media\Contracts\Photos;
use Modules\Media\Testing\Images;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Verification\Contracts\IdentityVerifier;
use Modules\Verification\Models\VerificationSession;

beforeEach(function () {
    Storage::fake('photos');
    config([
        'verification.didit.base_url' => 'https://verification.didit.me',
        'verification.didit.api_key' => 'didit-key',
        'verification.didit.workflow_id' => 'wf-123',
        'verification.didit.webhook_secret' => 'whsec',
    ]);
    $this->user = User::factory()->create();
    $this->token = tokenFor($this->user);
});

function withPhoto(): string
{
    test()->withToken(test()->token)->post('/api/v1/me/photo', ['photo' => Images::upload(Images::jpeg())], ['Accept' => 'application/json'])->assertOk();
    app('auth')->forgetGuards();

    return app(Photos::class)->current(test()->user->id)['id'];
}

function start(array $headers = [])
{
    return test()->withToken(test()->token)->postJson('/api/v1/me/verification', [], $headers);
}

function diditAccepts(): void
{
    $calls = 0;
    Http::fake(['verification.didit.me/*' => function () use (&$calls) {
        return Http::response([
            'session_id' => $calls++ === 0 ? '11111111-2222-3333-4444-555555555555' : (string) Str::uuid(),
            'session_token' => 'tok-abc123def456', 'status' => 'Not Started',
        ], 201);
    }]);
}

it('creates a Didit session with the stored photo and returns its token', function () {
    $photoId = withPhoto();
    diditAccepts();

    start(['Accept-Language' => 'ar'])->assertOk()->assertExactJson(['sessionToken' => 'tok-abc123def456']);

    Http::assertSent(function (Request $request) use ($photoId) {
        $body = $request->data();

        return $request->url() === 'https://verification.didit.me/v3/session/'
            && $request->method() === 'POST'
            && $request->hasHeader('x-api-key', 'didit-key')
            && $body['workflow_id'] === 'wf-123'
            && $body['vendor_data'] === test()->user->id
            && $body['language'] === 'ar'
            && base64_decode($body['portrait_image']) === app(Photos::class)->portrait($photoId);
    });
    expect(VerificationSession::sole())->session_id->toBe('11111111-2222-3333-4444-555555555555')
        ->photo_id->toBe($photoId)->user_id->toBe($this->user->id);
});

it('leaves the photo status alone until Didit reports back', function () {
    withPhoto();
    diditAccepts();
    start()->assertOk();

    expect(app(Photos::class)->current($this->user->id)['status'])->toBe('unverified');
});

it('defaults the Didit language to French and ignores unknown ones', function () {
    withPhoto();
    diditAccepts();
    start(['Accept-Language' => 'de-DE'])->assertOk();

    Http::assertSent(fn (Request $request) => $request->data()['language'] === 'fr');
});

it('needs a photo first', function () {
    diditAccepts();
    start()->assertStatus(422)->assertJsonPath('code', 'validation');
    Http::assertNothingSent();
});

it('does not re-check a verified photo', function () {
    $photoId = withPhoto();
    app(Photos::class)->setStatus($photoId, 'verified');
    diditAccepts();

    start()->assertStatus(422);
    Http::assertNothingSent();
});

it('answers provider_unavailable when Didit fails, and spends nothing', function (Closure $response) {
    withPhoto();
    config(['verification.sessions_per_day' => 1]);
    Http::fake(['verification.didit.me/*' => Http::sequence()->pushResponse($response())->push(['session_id' => 's-2', 'session_token' => 'tok-2'], 201)]);

    start()->assertStatus(503)->assertJsonPath('code', 'provider_unavailable');
    expect(VerificationSession::count())->toBe(0);

    start()->assertOk();
})->with([
    'refused' => [fn () => Http::response(['detail' => 'Invalid workflow'], 400)],
    'down' => [fn () => Http::response('oops', 502)],
]);

it('answers provider_unavailable when Didit cannot be reached', function () {
    withPhoto();
    Http::fake(['verification.didit.me/*' => fn () => throw new ConnectionException('timeout')]);
    start()->assertStatus(503);
});

it('is unavailable when Didit is not configured', function () {
    withPhoto();
    config(['verification.didit.api_key' => '']);
    start()->assertStatus(503);
});

it('allows 5 checks a day per person', function () {
    withPhoto();
    diditAccepts();
    foreach (range(1, 5) as $_) {
        start()->assertOk();
        $this->app['auth']->forgetGuards();
    }
    start()->assertStatus(429)->assertJsonPath('code', 'rate_limited');
});

it('sends Didit a portrait no larger than 1024 px', function () {
    test()->withToken(test()->token)->post('/api/v1/me/photo', ['photo' => Images::upload(Images::jpeg(1280, 1600))], ['Accept' => 'application/json'])->assertOk();
    app('auth')->forgetGuards();
    diditAccepts();

    start()->assertOk();

    Http::assertSent(function (Request $request) {
        [$width, $height] = getimagesizefromstring(base64_decode($request->data()['portrait_image']));

        return max($width, $height) === 1024;
    });
});

it('counts the check before calling Didit and refunds it on failure', function () {
    withPhoto();
    $seen = null;
    app()->instance(IdentityVerifier::class, new class($seen) implements IdentityVerifier
    {
        public function __construct(public ?int &$seen) {}

        public function createSession(string $userId, string $portraitJpeg, string $language): array
        {
            $this->seen = RateLimiter::attempts("verification:{$userId}");
            throw new ApiException(ApiErrorCode::ProviderUnavailable, 'down');
        }

        public function deleteSession(string $sessionId): void {}
    });

    start()->assertStatus(503);

    expect($seen)->toBe(1);
    expect(RateLimiter::attempts('verification:'.test()->user->id))->toBe(0);
});

it('stops all checks for the day past the global budget', function () {
    config(['verification.global_sessions_per_day' => 1]);
    withPhoto();
    diditAccepts();
    start()->assertOk();

    $other = User::factory()->create();
    test()->token = tokenFor($other);
    test()->user = $other;
    app('auth')->forgetGuards();
    withPhoto();
    start()->assertStatus(429);
});

it('reads the language case-insensitively', function () {
    withPhoto();
    diditAccepts();
    start(['Accept-Language' => 'AR-tn'])->assertOk();

    Http::assertSent(fn (Request $request) => $request->data()['language'] === 'ar');
});
