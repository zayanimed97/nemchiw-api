<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Http\PublicProfileResource;
use Modules\Identity\Models\User;
use Modules\Media\Contracts\Photos;
use Modules\Media\Models\Photo;
use Modules\Media\Testing\Images;

beforeEach(function () {
    Storage::fake('photos');
    $this->user = User::factory()->create();
    $this->token = tokenFor($this->user);
});

function upload(string $bytes, string $name = 'photo.jpg', array $headers = [])
{
    return test()->withToken(test()->token)->post('/api/v1/me/photo', ['photo' => Images::upload($bytes, $name)], $headers + ['Accept' => 'application/json']);
}

function storedBytes(): string
{
    return Storage::disk('photos')->get(Photo::sole()->path);
}

it('stores an upload and shows it, unverified, on the profile', function () {
    $response = upload(Images::jpeg())->assertOk();

    $photo = Photo::sole();
    expect($response->json('photo'))->toBe(['url' => url("/api/v1/photos/{$photo->id}"), 'status' => 'unverified']);
    expect(getimagesizefromstring(storedBytes())[2])->toBe(IMAGETYPE_JPEG);
});

it('re-encodes PNG to JPEG and shrinks big images', function () {
    upload(Images::png(3000, 2000), 'photo.png')->assertOk();

    [$width, $height, $type] = getimagesizefromstring(storedBytes());
    expect([$width, $height, $type])->toBe([1600, 1067, IMAGETYPE_JPEG]);
});

it('drops EXIF data such as GPS', function () {
    expect(Images::jpegWithExif())->toContain('GPSLatitude');
    upload(Images::jpegWithExif())->assertOk();

    expect(storedBytes())->not->toContain('GPSLatitude')->not->toContain('Exif');
});

it('never writes a payload hidden in an image to disk', function () {
    upload(Images::jpeg().'<?php system($_GET["c"]); ?>')->assertOk();
    expect(storedBytes())->not->toContain('<?php');
});

it('refuses anything that is not a JPEG or PNG photo', function (Closure $bytes, string $name) {
    upload($bytes(), $name)->assertStatus(422)->assertJsonPath('code', 'validation');
    expect(Photo::count())->toBe(0);
    expect(Storage::disk('photos')->allFiles())->toBe([]);
})->with([
    'PHP named .jpg' => [fn () => '<?php echo "hi"; ?>', 'photo.jpg'],
    'GIF' => [fn () => Images::gif(), 'photo.gif'],
    'decompression bomb' => [fn () => Images::hugePngHeader(), 'photo.png'],
    'truncated JPEG' => [fn () => substr(Images::jpeg(), 0, 200), 'photo.jpg'],
]);

it('refuses files over 5 MB', function () {
    $this->withToken($this->token)->post('/api/v1/me/photo', ['photo' => UploadedFile::fake()->create('big.jpg', 5121, 'image/jpeg')], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('lets the photo route take a big body while other routes keep 64 KB', function () {
    $this->withToken($this->token)->post('/api/v1/me/photo', [], ['Content-Length' => '5000000', 'Accept' => 'application/json'])->assertStatus(422);
    $this->withToken($this->token)->patchJson('/api/v1/me', [], ['Content-Length' => '5000000'])->assertStatus(413);
});

it('replaces the old photo and resets verification', function () {
    upload(Images::jpeg())->assertOk();
    $old = Photo::sole();
    app(Photos::class)->setStatus($old->id, 'verified');

    upload(Images::jpeg(600, 750))->assertOk()->assertJsonPath('photo.status', 'unverified');

    expect(Photo::count())->toBe(1);
    expect(Photo::sole()->id)->not->toBe($old->id);
    expect(Storage::disk('photos')->exists($old->path))->toBeFalse();
});

it('serves the photo to its owner', function () {
    upload(Images::jpeg())->assertOk();
    $this->app['auth']->forgetGuards();

    $response = $this->withToken($this->token)->get('/api/v1/photos/'.Photo::sole()->id)->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/jpeg');
    expect($response->headers->get('Cache-Control'))->toContain('private');
});

it('hides an unverified photo from everyone else, shows it once verified', function () {
    upload(Images::jpeg())->assertOk();
    $photo = Photo::sole();
    $this->app['auth']->forgetGuards();
    $other = tokenFor(User::factory()->create());

    $this->withToken($other)->get("/api/v1/photos/{$photo->id}")->assertNotFound();
    $this->app['auth']->forgetGuards();
    app(Photos::class)->setStatus($photo->id, 'verified');
    $this->withToken($other)->get("/api/v1/photos/{$photo->id}")->assertOk();
});

it('requires a token for photos', function () {
    upload(Images::jpeg())->assertOk();
    $this->app['auth']->forgetGuards();
    $this->withHeaders(['Authorization' => ''])->get('/api/v1/photos/'.Photo::sole()->id)->assertStatus(401);
});

it('shows others the photo only once verified', function () {
    upload(Images::jpeg())->assertOk();
    $photo = Photo::sole();
    $public = fn () => PublicProfileResource::make($this->user->fresh())->resolve(request());

    expect($public()['photo'])->toBeNull();
    app(Photos::class)->setStatus($photo->id, 'verified');
    expect($public()['photo'])->toBe(['url' => url("/api/v1/photos/{$photo->id}"), 'verified' => true]);
});

it('deletes the photo with the account', function () {
    upload(Images::jpeg())->assertOk();
    $path = Photo::sole()->path;
    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->deleteJson('/api/v1/me')->assertNoContent();

    expect(Photo::count())->toBe(0);
    expect(Storage::disk('photos')->exists($path))->toBeFalse();
});
