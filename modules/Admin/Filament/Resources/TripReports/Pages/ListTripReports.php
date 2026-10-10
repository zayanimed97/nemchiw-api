<?php

namespace Modules\Admin\Filament\Resources\TripReports\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Admin\Filament\Resources\TripReports\TripReportResource;

final class ListTripReports extends ListRecords
{
    protected static string $resource = TripReportResource::class;
}
