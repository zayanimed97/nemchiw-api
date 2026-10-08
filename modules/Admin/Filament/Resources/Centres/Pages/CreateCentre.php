<?php

namespace Modules\Admin\Filament\Resources\Centres\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Admin\Filament\Resources\Centres\CentreResource;
use Modules\Admin\Places\CreatesPlace;

final class CreateCentre extends CreateRecord
{
    use CreatesPlace;

    protected static string $resource = CentreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['open_months'] = array_map('intval', $data['open_months'] ?? []);

        return $data;
    }
}
