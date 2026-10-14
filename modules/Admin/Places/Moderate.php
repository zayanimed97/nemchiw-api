<?php

namespace Modules\Admin\Places;

use Modules\Shared\Places\PlaceModerated;
use Modules\Shared\Places\PlaceStatus;

final class Moderate
{
    public function approve(string $kind, string $id): void
    {
        $place = PlaceModels::find($kind, $id);
        $place->forceFill(['status' => PlaceStatus::LIVE, 'rejection_reason' => null])->save();
        event(new PlaceModerated($kind, $id, PlaceStatus::LIVE, $place->getAttribute('created_by'), (array) $place->getAttribute('name')));
    }

    public function reject(string $kind, string $id, string $reason): void
    {
        $place = PlaceModels::find($kind, $id);
        $place->forceFill(['status' => PlaceStatus::REJECTED, 'rejection_reason' => $reason])->save();
        event(new PlaceModerated($kind, $id, PlaceStatus::REJECTED, $place->getAttribute('created_by'), (array) $place->getAttribute('name')));
    }
}
