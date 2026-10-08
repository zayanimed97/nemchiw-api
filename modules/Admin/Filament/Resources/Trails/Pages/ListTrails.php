<?php

namespace Modules\Admin\Filament\Resources\Trails\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Admin\Filament\Resources\Trails\TrailResource;

final class ListTrails extends ListRecords
{
    protected static string $resource = TrailResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
