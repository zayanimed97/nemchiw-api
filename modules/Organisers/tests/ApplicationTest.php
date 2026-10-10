<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Models\User;
use Modules\Organisers\Actions\DecideApplication;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Organisers\Models\OrganiserApplication;

beforeEach(fn () => Storage::fake('local'));

function fields(): array
{
    return ['name' => 'Rando Nord', 'regions' => ['jendouba', 'beja'], 'experienceYears' => 6, 'publicPhone' => '+21671000000'];
}

function uploadAll($test, string $token): void
{
    foreach (['id_front', 'id_back', 'licence', 'insurance'] as $type) {
        $test->withToken($token)
            ->post("/api/v1/me/organiser-application/documents/{$type}", ['file' => UploadedFile::fake()->image("{$type}.jpg")])
            ->assertOk();
    }
}

it('says there is no application yet', function () {
    $user = tripReady();

    $this->withToken(tokenFor($user))->getJson('/api/v1/me/organiser-application')
        ->assertOk()->assertExactJson(['status' => 'none', 'certified' => false]);
});

it('only lets trip-ready people apply', function () {
    $user = User::factory()->create();

    $this->withToken(tokenFor($user))->putJson('/api/v1/me/organiser-application', fields())
        ->assertStatus(422)->assertJsonPath('code', 'not_trip_ready');
});

it('builds a draft, stores documents privately and sends it for review', function () {
    $user = tripReady();
    $token = tokenFor($user);

    $this->withToken($token)->putJson('/api/v1/me/organiser-application', fields())
        ->assertOk()->assertJsonPath('status', 'draft')->assertJsonPath('documents.licence', false);
    uploadAll($this, $token);

    $application = OrganiserApplication::query()->where('user_id', $user->id)->sole();
    expect($application->documents)->toHaveCount(4);
    foreach ($application->documents as $document) {
        Storage::disk('local')->assertExists($document->path);
        expect($document->path)->toStartWith("organiser-documents/{$application->id}/");
    }

    $this->withToken($token)->postJson('/api/v1/me/organiser-application/submit')
        ->assertOk()->assertJsonPath('status', 'submitted');
});

it('will not send an application with a missing document', function () {
    $user = tripReady();
    $token = tokenFor($user);
    $this->withToken($token)->putJson('/api/v1/me/organiser-application', fields());
    $this->withToken($token)->post('/api/v1/me/organiser-application/documents/id_front', ['file' => UploadedFile::fake()->image('a.jpg')]);

    $this->withToken($token)->postJson('/api/v1/me/organiser-application/submit')
        ->assertStatus(422)->assertJsonPath('code', 'validation');
});

it('replaces a document of the same type and deletes the old file', function () {
    $user = tripReady();
    $token = tokenFor($user);
    $this->withToken($token)->putJson('/api/v1/me/organiser-application', fields());
    $this->withToken($token)->post('/api/v1/me/organiser-application/documents/licence', ['file' => UploadedFile::fake()->image('old.jpg')]);
    $old = OrganiserApplication::query()->sole()->documents->sole()->path;

    $this->withToken($token)->post('/api/v1/me/organiser-application/documents/licence', ['file' => UploadedFile::fake()->create('new.pdf', 200, 'application/pdf')]);

    Storage::disk('local')->assertMissing($old);
    expect(OrganiserApplication::query()->sole()->documents)->toHaveCount(1);
});

it('rejects files that are not images or PDFs, or over 10 MB', function () {
    $user = tripReady();
    $token = tokenFor($user);
    $this->withToken($token)->putJson('/api/v1/me/organiser-application', fields());

    $this->withToken($token)->post('/api/v1/me/organiser-application/documents/licence', ['file' => UploadedFile::fake()->create('a.exe', 10)])
        ->assertStatus(422);
    $this->withToken($token)->post('/api/v1/me/organiser-application/documents/licence', ['file' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')])
        ->assertStatus(422);
});

it('locks the application while it is in review, reopens it after a refusal', function () {
    $user = tripReady();
    $token = tokenFor($user);
    $this->withToken($token)->putJson('/api/v1/me/organiser-application', fields());
    uploadAll($this, $token);
    $this->withToken($token)->postJson('/api/v1/me/organiser-application/submit');

    $this->withToken($token)->putJson('/api/v1/me/organiser-application', fields())
        ->assertStatus(409)->assertJsonPath('code', 'already_requested');

    app(DecideApplication::class)->reject(OrganiserApplication::query()->sole(), 'Licence is unreadable');
    $this->withToken($token)->getJson('/api/v1/me/organiser-application')
        ->assertJsonPath('status', 'rejected')->assertJsonPath('rejectionReason', 'Licence is unreadable');

    $this->withToken($token)->putJson('/api/v1/me/organiser-application', fields())
        ->assertOk()->assertJsonPath('status', 'draft');
});

it('certifies on approval and uncertifies on revocation', function () {
    $user = tripReady();
    $token = tokenFor($user);
    $this->withToken($token)->putJson('/api/v1/me/organiser-application', fields());
    uploadAll($this, $token);
    $this->withToken($token)->postJson('/api/v1/me/organiser-application/submit');
    $application = OrganiserApplication::query()->sole();

    app(DecideApplication::class)->approve($application);
    expect(app(OrganiserRecords::class)->isCertified($user->id))->toBeTrue();
    expect(app(OrganiserRecords::class)->publicPhone($user->id))->toBe('+21671000000');
    $this->withToken($token)->getJson('/api/v1/me/organiser-application')->assertJsonPath('certified', true);

    app(DecideApplication::class)->revoke($application->fresh(), 'Insurance expired');
    expect(app(OrganiserRecords::class)->isCertified($user->id))->toBeFalse();
});
