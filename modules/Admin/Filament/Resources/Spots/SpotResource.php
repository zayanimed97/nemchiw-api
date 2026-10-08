<?php

namespace Modules\Admin\Filament\Resources\Spots;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Admin\Places\PlaceForm;
use Modules\Shared\Places\PlaceStatus;
use Modules\Shared\Support\Governorates;
use Modules\Spots\Models\Spot;

final class SpotResource extends Resource
{
    protected static ?string $model = Spot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-fire';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...PlaceForm::common(),
            Select::make('type')->required()->options(array_combine(Spot::TYPES, Spot::TYPES)),
            Select::make('access')->required()->options(array_combine(Spot::ACCESS, Spot::ACCESS)),
            Toggle::make('water')->label('Water nearby'),
            ...PlaceForm::signalAndPermit(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->searchable(),
                TextColumn::make('name.en')->label('Name')->searchable(),
                TextColumn::make('governorate')->sortable(),
                TextColumn::make('type'),
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
            'index' => Pages\ListSpots::route('/'),
            'create' => Pages\CreateSpot::route('/create'),
            'edit' => Pages\EditSpot::route('/{record}/edit'),
            'photos' => Pages\ManageSpotPhotos::route('/{record}/photos'),
        ];
    }
}
