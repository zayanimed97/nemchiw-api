<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Models\User;
use Modules\Media\Testing\Images;
use Modules\Organisers\Models\Organiser;
use Modules\Spots\Models\Spot;
use Modules\Trails\Models\Trail;

beforeEach(fn () => Storage::fake('place_photos'));

function spotBody(array $overrides = []): array
{
    return array_merge(['name' => 'Oued Zitoun', 'description' => 'Olive grove by the river.', 'latitude' => 36.71,
        'longitude' => 9.12, 'governorate' => 'beja', 'coverage' => 'weak', 'permit' => 'no',
        'type' => 'forest', 'access' => 'car', 'water' => true], $overrides);
}

it('puts a certified organiser\'s place live at once', function () {
    $momen = tripReady();
    Organiser::query()->create(['user_id' => $momen->id, 'certified_at' => now()]);

    $response = $this->withToken(tokenFor($momen))->postJson('/api/v1/places/spot', spotBody())
        ->assertCreated()->assertJsonPath('status', 'live')->assertJsonPath('kind', 'spot');

    expect(Spot::findOrFail($response->json('id')))->status->toBe('live')->created_by->toBe($momen->id);
});

it('holds a community host\'s place for review', function () {
    $host = tripReady();

    $this->withToken(tokenFor($host))->postJson('/api/v1/places/trail', [
        'name' => 'Ras el Ain loop', 'description' => 'Short loop.', 'latitude' => 36.4, 'longitude' => 10.1,
        'governorate' => 'zaghouan', 'coverage' => 'good', 'permit' => 'no', 'distanceKm' => 6.5,
        'elevationGainM' => 300, 'durationMin' => 150, 'difficulty' => 'easy', 'shape' => 'loop', 'waterOnRoute' => false,
    ])->assertCreated()->assertJsonPath('status', 'pending');

    expect(Trail::query()->where('created_by', $host->id)->sole()->status)->toBe('pending');
});

it('needs a trip-ready person and refuses centres and bad values', function () {
    $this->withToken(tokenFor(User::factory()->create()))->postJson('/api/v1/places/spot', spotBody())
        ->assertStatus(422)->assertJsonPath('code', 'not_trip_ready');
    $this->withToken(tokenFor(tripReady()))->postJson('/api/v1/places/centre', spotBody())->assertNotFound();
    $this->withToken(tokenFor(tripReady()))->postJson('/api/v1/places/spot', spotBody(['latitude' => 45]))->assertStatus(422);
    $this->withToken(tokenFor(tripReady()))->postJson('/api/v1/places/spot', spotBody(['type' => 'lake']))->assertStatus(422);
});

it('lets the submitter add up to six photos, nobody else', function () {
    $host = tripReady();
    $id = $this->withToken(tokenFor($host))->postJson('/api/v1/places/spot', spotBody())->json('id');
    config(['media.uploads_per_hour' => 100]);

    foreach (range(1, 6) as $i) {
        $this->withToken(tokenFor($host))
            ->post("/api/v1/places/spot/{$id}/photos", ['photo' => UploadedFile::fake()->createWithContent("p{$i}.jpg", Images::jpeg(800 + $i, 600))])
            ->assertOk()->assertJsonCount($i, 'photos');
    }
    $this->withToken(tokenFor($host))
        ->post("/api/v1/places/spot/{$id}/photos", ['photo' => UploadedFile::fake()->createWithContent('p7.jpg', Images::jpeg(900, 600))])
        ->assertStatus(422);
    $this->withToken(tokenFor(tripReady()))
        ->post("/api/v1/places/spot/{$id}/photos", ['photo' => UploadedFile::fake()->createWithContent('x.jpg', Images::jpeg())])
        ->assertStatus(403);
});
