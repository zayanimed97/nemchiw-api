<?php

namespace Modules\Admin\Places;

use Modules\Shared\Places\PlaceStatus;

final class Moderate
{
    public function approve(string $kind, string $id): void
    {
        PlaceModels::find($kind, $id)->forceFill(['status' => PlaceStatus::LIVE, 'rejection_reason' => null])->save();
    }

    public function reject(string $kind, string $id, string $reason): void
    {
        PlaceModels::find($kind, $id)->forceFill(['status' => PlaceStatus::REJECTED, 'rejection_reason' => $reason])->save();
    }
}
