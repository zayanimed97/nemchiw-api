<?php

namespace Modules\Admin\Places;

use Illuminate\Support\Str;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Shared\Places\PlaceDirectory;
use Modules\Shared\Places\PlaceKind;
use Modules\Shared\Places\PlaceStatus;
use Modules\Spots\Models\Spot;
use Modules\Trails\Models\Trail;

final class EloquentPlaceDirectory implements PlaceDirectory
{
    public function __construct(private readonly PlacePhotos $photos) {}

    public function find(string $kind, string $id): ?array
    {
        $model = PlaceModels::ALL[$kind] ?? null;
        $place = $model === null ? null : $model::query()->find($id);
        if ($place === null) {
            return null;
        }

        return [
            'kind' => $kind,
            'id' => $place->getKey(),
            'name' => $place->name,
            'governorate' => $place->governorate,
            'latitude' => (float) $place->latitude,
            'longitude' => (float) $place->longitude,
            'status' => $place->status,
            'cover' => $this->photos->hashesFor($kind, [$id])[$id][0] ?? null,
            'createdBy' => $place->created_by,
        ];
    }

    public function submit(string $kind, array $fields, string $userId, bool $live): string
    {
        $text = fn (string $value) => ['ar' => $value, 'fr' => $value, 'en' => $value];
        $common = [
            'name' => $text($fields['name']),
            'description' => $text($fields['description']),
            'latitude' => $fields['latitude'],
            'longitude' => $fields['longitude'],
            'governorate' => $fields['governorate'],
            'coverage' => $fields['coverage'],
            'permit' => $fields['permit'],
            'status' => $live ? PlaceStatus::LIVE : PlaceStatus::PENDING,
        ];

        $place = match ($kind) {
            PlaceKind::SPOT => new Spot(array_merge($common, [
                'type' => $fields['type'], 'access' => $fields['access'], 'water' => $fields['water'],
            ])),
            PlaceKind::TRAIL => new Trail(array_merge($common, [
                'distance_km' => $fields['distanceKm'], 'elevation_gain_m' => $fields['elevationGainM'],
                'duration_min' => $fields['durationMin'], 'difficulty' => $fields['difficulty'],
                'shape' => $fields['shape'], 'water_on_route' => $fields['waterOnRoute'],
            ])),
            default => throw new \InvalidArgumentException("Organisers cannot submit a {$kind}"),
        };
        $place->setAttribute('id', $this->newId($kind, $fields['name']));
        $place->setAttribute('created_by', $userId);
        $place->save();

        return $place->getKey();
    }

    /** "oued-zitoun-k3x9q": a readable slug plus a random tail, never starting with "example-". */
    private function newId(string $kind, string $name): string
    {
        $slug = Str::limit(Str::slug(Str::ascii($name)), 50, '') ?: $kind;
        if (str_starts_with($slug, 'example')) {
            $slug = 'place-'.$slug;
        }
        do {
            $id = $slug.'-'.Str::lower(Str::random(5));
        } while (PlaceModels::ALL[$kind]::withTrashed()->whereKey($id)->exists());

        return $id;
    }
}
