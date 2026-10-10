<?php

namespace Modules\Admin\Filament\Resources\TripReports;

use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Admin\Models\AdminAction;
use Modules\Trips\Actions\ResolveReport;
use Modules\Trips\Models\TripReport;

final class TripReportResource extends Resource
{
    protected static ?string $model = TripReport::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Trip reports';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = TripReport::query()->where('status', 'open')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        $resolve = fn (string $name, string $label, string $outcome, string $color) => Action::make($name)
            ->label($label)->color($color)->requiresConfirmation()
            ->visible(fn (TripReport $record) => $record->status === 'open')
            ->action(function (TripReport $record) use ($outcome) {
                app(ResolveReport::class)($record, $outcome, (string) auth('admin')->id());
                AdminAction::query()->create(['admin_id' => auth('admin')->id(), 'action' => substr($outcome, 0, 16),
                    'target_kind' => 'trip_report', 'target_id' => $record->id, 'changes' => null]);
            });

        return $table
            ->columns([
                TextColumn::make('reason')->badge(),
                TextColumn::make('note')->wrap(),
                TextColumn::make('request.trip.title')->label('Trip'),
                TextColumn::make('request.status')->label('Request')->badge(),
                TextColumn::make('reporter_id')->label('Reporter')->copyable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->defaultSort('created_at')
            ->filters([SelectFilter::make('status')->options(array_combine(
                ['open', 'closed_no_penalty', 'closed_organiser', 'dismissed'],
                ['open', 'closed_no_penalty', 'closed_organiser', 'dismissed'],
            ))->default('open')])
            ->recordActions([
                $resolve('closeNoPenalty', 'Close, no penalty', 'closed_no_penalty', 'gray'),
                $resolve('closeOrganiser', 'Close as organiser cancellation', 'closed_organiser', 'danger'),
                $resolve('dismiss', 'Dismiss', 'dismissed', 'gray'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTripReports::route('/')];
    }
}
