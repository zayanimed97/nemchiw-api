<?php

namespace Modules\Admin\Filament\Resources\Trails\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Admin\Filament\Resources\Trails\TrailResource;

final class EditTrail extends EditRecord
{
    protected static string $resource = TrailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('photos')->url(fn () => TrailResource::getUrl('photos', ['record' => $this->record])),
            DeleteAction::make(),
        ];
    }
}
