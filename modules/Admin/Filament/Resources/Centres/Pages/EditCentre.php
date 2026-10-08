<?php

namespace Modules\Admin\Filament\Resources\Centres\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Admin\Filament\Resources\Centres\CentreResource;

final class EditCentre extends EditRecord
{
    protected static string $resource = CentreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('photos')->url(fn () => CentreResource::getUrl('photos', ['record' => $this->record])),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['open_months'] = array_map('intval', $data['open_months'] ?? []);

        return $data;
    }
}
