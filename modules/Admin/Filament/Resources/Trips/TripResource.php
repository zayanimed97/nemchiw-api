<?php

namespace Modules\Admin\Filament\Resources\Trips;

use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Admin\Models\AdminAction;
use Modules\Trips\Actions\CancelTrip;
use Modules\Trips\Models\Trip;

final class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('kind')->badge(),
                TextColumn::make('destination.name.en')->label('Place'),
                TextColumn::make('organiser_id')->label('Organiser')->copyable(),
                TextColumn::make('starts_at')->dateTime()->sortable(),
                TextColumn::make('seats'),
                TextColumn::make('price_tnd')->label('TND'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([SelectFilter::make('status')->options(array_combine(
                ['draft', 'published', 'cancelled', 'finished'], ['draft', 'published', 'cancelled', 'finished'],
            ))])
            ->recordActions([
                Action::make('takeDown')->label('Take down')->color('danger')->requiresConfirmation()
                    ->modalDescription('Cancels the trip for everyone. It does not count against the organiser.')
                    ->visible(fn (Trip $record) => in_array($record->status, ['draft', 'published'], true))
                    ->action(function (Trip $record) {
                        app(CancelTrip::class)($record, 'admin', counts: false);
                        AdminAction::query()->create(['admin_id' => auth('admin')->id(), 'action' => 'taken_down',
                            'target_kind' => 'trip', 'target_id' => $record->id, 'changes' => null]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTrips::route('/')];
    }
}
