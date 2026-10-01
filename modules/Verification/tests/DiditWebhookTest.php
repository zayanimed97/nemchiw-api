<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Models\User;
use Modules\Media\Contracts\Photos;
use Modules\Media\Services\PhotoStore;
use Modules\Media\Testing\Images;
use Modules\Verification\Actions\PruneWebhookEvents;
use Modules\Verification\Models\VerificationSession;

beforeEach(function () {
    Storage::fake('photos');
    config(['verification.didit.webhook_secret' => 'whsec']);
    $this->user = User::factory()->create();
    $this->photoId = app(PhotoStore::class)->replace($this->user->id, Images::jpeg())->id;
    (new VerificationSession)->forceFill(['session_id' => 'sess-1', 'user_id' => $this->user->id, 'photo_id' => $this->photoId])->save();
});

function diditEvent(string $status, array $decision = [], string $eventId = 'evt-1', string $session = 'sess-1'): array
{
    return [
        'event_id' => $eventId, 'webhook_type' => 'status.updated', 'timestamp' => now()->getTimestamp(),
        'session_id' => $session, 'status' => $status, 'vendor_data' => 'whoever', 'decision' => $decision ?: null,
    ];
}

/** Posts the exact bytes Didit would, signed over the raw body. */
function deliver(array $payload, ?string $secret = 'whsec', ?int $timestamp = null, ?string $sent = null)
{
    $raw = json_encode($payload);
    $headers = ['Content-Type' => 'application/json', 'X-Timestamp' => (string) ($timestamp ?? now()->getTimestamp())];
    if ($secret !== null) {
        $headers['X-Signature'] = hash_hmac('sha256', $raw, $secret);
    }
    // $sent: what actually arrives, e.g. the signed body edited on the way.
    $raw = $sent ?? $raw;

    return test()->call('POST', '/api/v1/webhooks/didit', [], [], [], test()->transformHeadersToServerVars($headers), $raw);
}

function photoStatus(): string
{
    return app(Photos::class)->current(test()->user->id)['status'];
}

it('maps Didit statuses to the photo', function (string $didit, array $decision, string $expected) {
    deliver(diditEvent($didit, $decision))->assertOk();
    expect(photoStatus())->toBe($expected);
})->with([
    'in progress' => ['In Progress', [], 'pending'],
    'in review' => ['In Review', [], 'pending'],
    'approved' => ['Approved', ['liveness_checks' => [['status' => 'Approved']], 'face_matches' => [['status' => 'Approved']]], 'verified'],
    'approved, no detail' => ['Approved', [], 'verified'],
    'declined' => ['Declined', [], 'rejected'],
    'approved but face declined' => ['Approved', ['liveness_checks' => [['status' => 'Approved']], 'face_matches' => [['status' => 'Declined']]], 'rejected'],
    'abandoned' => ['Abandoned', [], 'unverified'],
    'expired' => ['Expired', [], 'unverified'],
]);

it('puts a pending photo back to unverified when the check is abandoned', function () {
    deliver(diditEvent('In Progress'))->assertOk();
    deliver(diditEvent('Abandoned', eventId: 'evt-2'))->assertOk();
    expect(photoStatus())->toBe('unverified');
});

it('accepts the canonical V2 signature', function () {
    $payload = ['session_id' => 'sess-1', 'event_id' => 'evt-v2', 'status' => 'Approved', 'timestamp' => now()->getTimestamp(),
        'decision' => ['note' => 'é/x', 'score' => 100.0], 'webhook_type' => 'status.updated'];
    // Didit's canonical form: keys sorted, no spaces, unicode and slashes unescaped, 100.0 → 100.
    $canonical = '{"decision":{"note":"é/x","score":100},"event_id":"evt-v2","session_id":"sess-1","status":"Approved","timestamp":'
        .now()->getTimestamp().',"webhook_type":"status.updated"}';
    $raw = json_encode($payload); // different bytes: key order, \/ escaping, é, 100.0

    test()->call('POST', '/api/v1/webhooks/didit', [], [], [], test()->transformHeadersToServerVars([
        'Content-Type' => 'application/json', 'X-Timestamp' => (string) now()->getTimestamp(),
        'X-Signature-V2' => hash_hmac('sha256', $canonical, 'whsec'),
    ]), $raw)->assertOk();

    expect(photoStatus())->toBe('verified');
});

it('refuses forged, stale and unsigned deliveries without changing anything', function (Closure $send) {
    $send()->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
    expect(photoStatus())->toBe('unverified');
})->with([
    'other secret' => [fn () => deliver(diditEvent('Approved'), secret: 'guess')],
    'edited body' => [fn () => deliver(diditEvent('Declined'), sent: json_encode(diditEvent('Approved')))],
    'stale' => [fn () => deliver(diditEvent('Approved'), timestamp: now()->subMinutes(6)->getTimestamp())],
    'from the future' => [fn () => deliver(diditEvent('Approved'), timestamp: now()->addMinutes(6)->getTimestamp())],
    'unsigned' => [fn () => deliver(diditEvent('Approved'), secret: null)],
]);

it('refuses everything when no webhook secret is configured', function () {
    config(['verification.didit.webhook_secret' => '']);
    deliver(diditEvent('Approved'), secret: '')->assertStatus(401);
    expect(photoStatus())->toBe('unverified');
});

it('applies each event once', function () {
    deliver(diditEvent('Approved'))->assertOk();
    app(Photos::class)->setStatus($this->photoId, 'unverified');

    deliver(diditEvent('Approved'))->assertOk();
    expect(photoStatus())->toBe('unverified');
});

it('answers 200 to an unknown session and changes nothing', function () {
    deliver(diditEvent('Approved', session: 'not-ours'))->assertOk();
    expect(photoStatus())->toBe('unverified');
});

it('never trusts vendor_data to find the person', function () {
    $other = User::factory()->create();
    $payload = diditEvent('Approved', session: 'not-ours');
    $payload['vendor_data'] = $other->id;

    deliver($payload)->assertOk();
    expect(VerificationSession::find('not-ours'))->toBeNull();
});

it('ignores a late decision for a photo that has been replaced', function () {
    app(PhotoStore::class)->replace($this->user->id, Images::jpeg(600, 750));

    deliver(diditEvent('Approved'))->assertOk();
    expect(photoStatus())->toBe('unverified');
});

it('keeps a badge earned by a newer session when an older one is abandoned', function () {
    $this->travel(1)->seconds();
    (new VerificationSession)->forceFill(['session_id' => 'sess-2', 'user_id' => $this->user->id, 'photo_id' => $this->photoId])->save();

    deliver(diditEvent('Approved', eventId: 'evt-b', session: 'sess-2'))->assertOk();
    deliver(diditEvent('Abandoned', eventId: 'evt-a', session: 'sess-1'))->assertOk();
    deliver(diditEvent('Declined', eventId: 'evt-a2', session: 'sess-1'))->assertOk();

    expect(photoStatus())->toBe('verified');
});

it('ignores a retried earlier status that arrives after the decision', function () {
    deliver(diditEvent('Approved', eventId: 'evt-2'))->assertOk();
    deliver(diditEvent('In Progress', eventId: 'evt-1'))->assertOk();

    expect(photoStatus())->toBe('verified');
});

it('acts only on session status updates', function () {
    $payload = diditEvent('Approved');
    $payload['webhook_type'] = 'data.updated';

    deliver($payload)->assertOk();
    expect(photoStatus())->toBe('unverified');
});

it('accepts large signed decisions', function () {
    $payload = diditEvent('Approved', ['liveness_checks' => [['status' => 'Approved', 'blob' => str_repeat('x', 100_000)]]]);
    deliver($payload)->assertOk();
    expect(photoStatus())->toBe('verified');
});

it('prunes webhook events older than a day', function () {
    deliver(diditEvent('In Progress', eventId: 'old'))->assertOk();
    $this->travel(25)->hours();
    deliver(diditEvent('Approved', eventId: 'new'))->assertOk();

    app(PruneWebhookEvents::class)();

    expect(DB::table('webhook_events')->pluck('event_id')->all())->toBe(['new']);
});
