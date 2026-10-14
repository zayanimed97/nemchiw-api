<?php

use Illuminate\Support\Facades\Storage;
use Modules\Media\Contracts\ChatPhotos;
use Modules\Media\Testing\Images;
use Modules\Shared\Errors\ApiException;

beforeEach(fn () => Storage::fake('local'));

it('stores two private WebP sizes without EXIF', function () {
    $photos = app(ChatPhotos::class);

    $hash = $photos->store('conv1', Images::jpegWithExif());

    expect($hash)->toMatch('/^[0-9a-f]{32}$/');
    foreach ([480, 1280] as $size) {
        $path = $photos->path('conv1', $hash, $size);
        expect($path)->not->toBeNull();
        $bytes = file_get_contents($path);
        expect(substr($bytes, 8, 4))->toBe('WEBP');
        expect($bytes)->not->toContain('GPSLatitude');
    }
    Storage::disk('local')->assertExists("chat/conv1/{$hash}-480.webp");
});

it('refuses what is not a photo', function () {
    app(ChatPhotos::class)->store('conv1', Images::gif());
})->throws(ApiException::class);

it('answers null for a missing photo and forgets a folder', function () {
    $photos = app(ChatPhotos::class);
    $hash = $photos->store('conv2', Images::jpeg());

    expect($photos->path('conv2', 'ffffffffffffffffffffffffffffffff', 480))->toBeNull();
    expect($photos->path('conv2', $hash, 999))->toBeNull();
    $photos->deleteFolder('conv2');
    expect($photos->path('conv2', $hash, 480))->toBeNull();
});
