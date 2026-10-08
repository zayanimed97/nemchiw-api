<?php

namespace Modules\Admin\Filament\Resources\Trails\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Admin\Filament\Resources\Trails\TrailResource;
use Modules\Admin\Places\CreatesPlace;

final class CreateTrail extends CreateRecord
{
    use CreatesPlace;

    protected static string $resource = TrailResource::class;
}
