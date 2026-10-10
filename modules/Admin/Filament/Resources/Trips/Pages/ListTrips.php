<?php

namespace Modules\Admin\Filament\Resources\Trips\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Admin\Filament\Resources\Trips\TripResource;

final class ListTrips extends ListRecords
{
    protected static string $resource = TripResource::class;
}
