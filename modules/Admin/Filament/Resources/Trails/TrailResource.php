<?php

namespace Modules\Admin\Filament\Resources\Trails;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
use Modules\Trails\Models\Trail;

final class TrailResource extends Resource
{
    protected static ?string $model = Trail::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...PlaceForm::common(),
            TextInput::make('distance_km')->label('Distance (km)')->numeric()->required()->minValue(0.1)->maxValue(999),
            TextInput::make('elevation_gain_m')->label('Elevation gain (m)')->integer()->required()->minValue(0)->maxValue(5000),
            TextInput::make('duration_min')->label('Duration (minutes)')->integer()->required()->minValue(10)->maxValue(5000),
            Select::make('difficulty')->required()->options(array_combine(Trail::DIFFICULTY, Trail::DIFFICULTY)),
            Select::make('shape')->required()->options(array_combine(Trail::SHAPES, Trail::SHAPES)),
            Toggle::make('water_on_route')->label('Water on the route'),
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
                TextColumn::make('difficulty'),
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
            'index' => Pages\ListTrails::route('/'),
            'create' => Pages\CreateTrail::route('/create'),
            'edit' => Pages\EditTrail::route('/{record}/edit'),
            'photos' => Pages\ManageTrailPhotos::route('/{record}/photos'),
        ];
    }
}
