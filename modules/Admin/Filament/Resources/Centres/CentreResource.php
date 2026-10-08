<?php

namespace Modules\Admin\Filament\Resources\Centres;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Admin\Places\PlaceForm;
use Modules\Centres\Models\Centre;
use Modules\Shared\Places\PlaceStatus;
use Modules\Shared\Support\Governorates;

final class CentreResource extends Resource
{
    protected static ?string $model = Centre::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...PlaceForm::common(),
            CheckboxList::make('amenities')->options(array_combine(Centre::AMENITIES, Centre::AMENITIES))->columns(2),
            TextInput::make('price_from_tnd')->label('From (TND per person per night)')->numeric()->minValue(0)->maxValue(10000),
            TextInput::make('phone')->required()->regex('/^\+216[2-9]\d{7}$/')->helperText('+216 and 8 digits'),
            TextInput::make('link')->url()->maxLength(255),
            CheckboxList::make('open_months')->required()->columns(6)
                ->options(array_combine(range(1, 12), ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->searchable(),
                TextColumn::make('name.en')->label('Name')->searchable(),
                TextColumn::make('governorate')->sortable(),
                TextColumn::make('price_from_tnd')->label('From (TND)'),
                TextColumn::make('status')->badge(),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(array_combine(PlaceStatus::ALL, PlaceStatus::ALL)),
                SelectFilter::make('governorate')->options(array_combine(Governorates::ALL, Governorates::ALL)),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCentres::route('/'),
            'create' => Pages\CreateCentre::route('/create'),
            'edit' => Pages\EditCentre::route('/{record}/edit'),
            'photos' => Pages\ManageCentrePhotos::route('/{record}/photos'),
        ];
    }
}
