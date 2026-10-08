<?php

namespace Modules\Admin\Places;

use Illuminate\Database\Eloquent\Model;
use Modules\Centres\Models\Centre;
use Modules\Shared\Places\PlaceKind;
use Modules\Spots\Models\Spot;
use Modules\Trails\Models\Trail;

final class PlaceModels
{
    /** @var array<string, class-string<Model>> */
    public const ALL = [PlaceKind::SPOT => Spot::class, PlaceKind::TRAIL => Trail::class, PlaceKind::CENTRE => Centre::class];

    public static function find(string $kind, string $id): Model
    {
        $model = self::ALL[$kind] ?? throw new \InvalidArgumentException("Unknown place kind {$kind}");

        return $model::query()->findOrFail($id);
    }

    public static function kindOf(Model $place): string
    {
        return array_search($place::class, self::ALL, true) ?: throw new \InvalidArgumentException($place::class);
    }
}
