<?php

namespace Modules\Admin\Filament\Resources\Centres\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Admin\Filament\Resources\Centres\CentreResource;

final class ListCentres extends ListRecords
{
    protected static string $resource = CentreResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
