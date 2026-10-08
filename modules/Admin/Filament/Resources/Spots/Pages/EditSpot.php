<?php

namespace Modules\Admin\Filament\Resources\Spots\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Admin\Filament\Resources\Spots\SpotResource;

final class EditSpot extends EditRecord
{
    protected static string $resource = SpotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('photos')->url(fn () => SpotResource::getUrl('photos', ['record' => $this->record])),
            DeleteAction::make(),
        ];
    }
}
