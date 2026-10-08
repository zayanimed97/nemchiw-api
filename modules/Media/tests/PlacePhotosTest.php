<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Media\Events\PlacePhotosChanged;
use Modules\Media\Testing\Images;
use Modules\Shared\Errors\ApiException;

beforeEach(function () {
    Storage::fake('place_photos');
    $this->photos = app(PlacePhotos::class);
});

it('stores both WebP sizes under the content hash', function () {
    $hash = $this->photos->add('trail', 'zaghouan-aqueduct', Images::jpeg(3000, 2000));

    expect($hash)->toMatch('/^[0-9a-f]{32}$/');
    Storage::disk('place_photos')->assertExists(["{$hash}-480.webp", "{$hash}-1280.webp"]);
    [$width] = getimagesizefromstring(Storage::disk('place_photos')->get("{$hash}-1280.webp"));
    expect($width)->toBe(1280);
    [$small] = getimagesizefromstring(Storage::disk('place_photos')->get("{$hash}-480.webp"));
    expect($small)->toBe(480);
    expect(hash('sha256', Storage::disk('place_photos')->get("{$hash}-1280.webp")))->toStartWith($hash);
});

it('keeps order, cover first, per place', function () {
    $a = $this->photos->add('spot', 'tabarka', Images::jpeg(800, 600));
    $b = $this->photos->add('spot', 'tabarka', Images::jpeg(600, 800));
    $this->photos->add('spot', 'ain-draham', Images::png());

    expect($this->photos->hashesFor('spot', ['tabarka', 'ain-draham', 'nowhere']))
        ->toHaveKeys(['tabarka', 'ain-draham'])
        ->and($this->photos->hashesFor('spot', ['tabarka'])['tabarka'])->toBe([$a, $b]);

    $this->photos->reorder('spot', 'tabarka', [$b, $a]);
    expect($this->photos->hashesFor('spot', ['tabarka'])['tabarka'])->toBe([$b, $a]);
});

it('ignores the same image added twice to one place', function () {
    $first = $this->photos->add('spot', 'tabarka', Images::jpeg());
    $second = $this->photos->add('spot', 'tabarka', Images::jpeg());

    expect($second)->toBe($first);
    expect($this->photos->hashesFor('spot', ['tabarka'])['tabarka'])->toBe([$first]);
});

it('refuses a seventh photo and leaves no file behind', function () {
    foreach (range(1, 6) as $i) {
        $this->photos->add('centre', 'ain-soltane', Images::jpeg(400 + $i, 300));
    }
    $before = Storage::disk('place_photos')->allFiles();

    expect(fn () => $this->photos->add('centre', 'ain-soltane', Images::jpeg(999, 300)))->toThrow(ApiException::class);
    expect(Storage::disk('place_photos')->allFiles())->toEqualCanonicalizing($before);
});

it('deletes files only when no place uses the hash any more', function () {
    $hash = $this->photos->add('spot', 'tabarka', Images::jpeg());
    $this->photos->add('trail', 'tabarka-coast', Images::jpeg());

    $this->photos->remove('spot', 'tabarka', $hash);
    Storage::disk('place_photos')->assertExists("{$hash}-480.webp");

    $this->photos->forgetPlace('trail', 'tabarka-coast');
    Storage::disk('place_photos')->assertMissing(["{$hash}-480.webp", "{$hash}-1280.webp"]);
});

it('rejects a reorder that is not the same set', function () {
    $a = $this->photos->add('spot', 'tabarka', Images::jpeg());

    expect(fn () => $this->photos->reorder('spot', 'tabarka', [$a, 'f'.str_repeat('0', 31)]))->toThrow(InvalidArgumentException::class);
});

it('announces every change so the place resyncs', function () {
    Event::fake([PlacePhotosChanged::class]);
    $hash = $this->photos->add('spot', 'tabarka', Images::jpeg());
    $this->photos->remove('spot', 'tabarka', $hash);

    Event::assertDispatchedTimes(PlacePhotosChanged::class, 2);
});

it('refuses files that are not JPEG or PNG', function () {
    $this->photos->add('spot', 'tabarka', Images::gif());
})->throws(ApiException::class);

it('builds public URLs', function () {
    expect($this->photos->url(str_repeat('a', 32), 480))->toEndWith('/media/places/'.str_repeat('a', 32).'-480.webp');
});
