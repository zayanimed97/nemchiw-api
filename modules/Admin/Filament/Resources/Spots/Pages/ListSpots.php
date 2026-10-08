<?php

namespace Modules\Admin\Filament\Resources\Spots\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Admin\Filament\Resources\Spots\SpotResource;

final class ListSpots extends ListRecords
{
    protected static string $resource = SpotResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
