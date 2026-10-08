<?php

namespace Modules\Admin\Filament\Resources\Spots\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Admin\Filament\Resources\Spots\SpotResource;
use Modules\Admin\Places\CreatesPlace;

final class CreateSpot extends CreateRecord
{
    use CreatesPlace;

    protected static string $resource = SpotResource::class;
}
