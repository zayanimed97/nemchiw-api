<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Spots\Actions\ImportSpots;
use Modules\Spots\Models\Spot;

function seedRows(): array
{
    return json_decode(file_get_contents(base_path('modules/Spots/database/data/spots.json')), true);
}

it('imports the 15 seed spots', function () {
    expect(app(ImportSpots::class)(seedRows()))->toBe(['created' => 15, 'updated' => 0, 'unchanged' => 0]);
    expect(Spot::count())->toBe(15);
});

it('leaves unchanged rows alone so clients do not resync them', function () {
    app(ImportSpots::class)(seedRows());
    $before = Spot::pluck('updated_at', 'id');

    $this->travel(1)->hours();
    expect(app(ImportSpots::class)(seedRows()))->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 15]);
    expect(Spot::pluck('updated_at', 'id'))->toEqual($before);
});

it('gives every changed row a distinct timestamp', function () {
    app(ImportSpots::class)(seedRows());
    expect(Spot::distinct()->count('updated_at'))->toBe(15);
});

it('rejects rows that break the contract', function (array $patch) {
    $rows = seedRows();
    $rows[0] = array_merge($rows[0], $patch);
    app(ImportSpots::class)($rows);
})->with([
    'unknown governorate' => [['governorate' => 'paris']],
    'outside Tunisia' => [['latitude' => 48.85]],
    'bad id' => [['id' => '../etc']],
    'unknown type' => [['type' => 'volcano']],
])->throws(ValidationException::class);

it('sees JSON reordered by the database as unchanged', function () {
    app(ImportSpots::class)(seedRows());
    // MySQL stores JSON objects with keys sorted by length, then alphabetically.
    foreach (Spot::all() as $spot) {
        DB::table('spots')->where('id', $spot->id)->update([
            'name' => json_encode(['ar' => $spot->name['ar'], 'en' => $spot->name['en'], 'fr' => $spot->name['fr']]),
            'description' => json_encode(['en' => $spot->description['en'], 'fr' => $spot->description['fr'], 'ar' => $spot->description['ar']]),
        ]);
    }

    expect(app(ImportSpots::class)(seedRows()))->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 15]);
});
