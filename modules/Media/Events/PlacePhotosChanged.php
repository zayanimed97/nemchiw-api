<?php

namespace Modules\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A place's photos changed: its module bumps updated_at so the app resyncs it. */
final class PlacePhotosChanged
{
    use Dispatchable;

    public function __construct(public readonly string $kind, public readonly string $placeId) {}
}
