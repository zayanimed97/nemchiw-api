<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Admin\Filament\Resources\Centres\Pages\CreateCentre;
use Modules\Admin\Filament\Resources\Trails\Pages\CreateTrail;
use Modules\Admin\Filament\Resources\Trails\Pages\ListTrails;
use Modules\Admin\Filament\Resources\Trails\Pages\ManageTrailPhotos;
use Modules\Admin\Models\Admin;
use Modules\Admin\Places\PlaceForm;
use Modules\Media\Testing\Images;
use Modules\Trails\Models\Trail;

beforeEach(fn () => $this->actingAs(Admin::factory()->create(), 'admin'));

function trailInput(array $patch = []): array
{
    return array_merge([
        'id' => 'zaghouan-aqueduct',
        'name' => ['ar' => 'ثنيّة الحنايا', 'fr' => 'Sentier de l’aqueduc', 'en' => 'Aqueduct trail'],
        'description' => ['ar' => 'وصف', 'fr' => 'Description', 'en' => 'Description'],
        'latitude' => 36.40,
        'longitude' => 10.14,
        'governorate' => 'zaghouan',
        'distance_km' => 9.5,
        'elevation_gain_m' => 380,
        'duration_min' => 200,
        'difficulty' => 'moderate',
        'shape' => 'oneWay',
        'water_on_route' => false,
        'coverage' => 'weak',
        'permit' => 'no',
        'status' => 'live',
    ], $patch);
}

it('creates a trail', function () {
    Livewire::test(CreateTrail::class)->fillForm(trailInput())->call('create')->assertHasNoFormErrors();

    expect(Trail::findOrFail('zaghouan-aqueduct')->shape)->toBe('oneWay');
});

it('rejects bad trail input', function (array $patch, string $field) {
    Livewire::test(CreateTrail::class)->fillForm(trailInput($patch))->call('create')->assertHasFormErrors([$field]);
})->with([
    'reserved id' => [['id' => 'example-trail'], 'id'],
    'outside Tunisia' => [['latitude' => 48.8], 'latitude'],
    'unknown difficulty' => [['difficulty' => 'extreme'], 'difficulty'],
    'missing arabic name' => [['name' => ['ar' => '', 'fr' => 'x', 'en' => 'x']], 'name.ar'],
]);

it('requires a Tunisian business phone for a centre', function () {
    Livewire::test(CreateCentre::class)->fillForm([
        'id' => 'ain-soltane', 'phone' => '12345',
    ])->call('create')->assertHasFormErrors(['phone']);
});

it('lists trails with a status filter', function () {
    $live = Trail::factory()->create();
    $hidden = Trail::factory()->create(['status' => 'hidden']);

    Livewire::test(ListTrails::class)
        ->filterTable('status', 'hidden')
        ->assertCanSeeTableRecords([$hidden])
        ->assertCanNotSeeTableRecords([$live]);
});

it('reads coordinates from text and Google Maps links', function (string $input, ?array $expected) {
    expect(PlaceForm::parseCoordinates($input))->toBe($expected);
})->with([
    'plain' => ['36.7712, 8.6871', [36.7712, 8.6871]],
    'google @' => ['https://www.google.com/maps/place/X/@36.7712,8.6871,15z', [36.7712, 8.6871]],
    'google q' => ['https://maps.google.com/?q=36.7712,8.6871', [36.7712, 8.6871]],
    'nonsense' => ['near the big tree', null],
]);

it('adds, reorders and removes photos from the photos page', function () {
    Storage::fake('place_photos');
    Storage::fake('local');
    $trail = Trail::factory()->create();
    $page = Livewire::test(ManageTrailPhotos::class, ['record' => $trail->id]);

    $page->callAction('upload', data: ['photo' => UploadedFile::fake()->createWithContent('a.jpg', Images::jpeg(800, 600))]);
    $page->callAction('upload', data: ['photo' => UploadedFile::fake()->createWithContent('b.jpg', Images::jpeg(600, 800))]);
    [$a, $b] = array_column($page->instance()->photos(), 'hash');

    $page->call('move', $b, -1);
    expect(array_column($page->instance()->photos(), 'hash'))->toBe([$b, $a]);

    $page->call('remove', $b);
    expect(array_column($page->instance()->photos(), 'hash'))->toBe([$a]);
});
