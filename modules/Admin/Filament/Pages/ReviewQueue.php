<?php

namespace Modules\Admin\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Modules\Admin\Places\Moderate;
use Modules\Admin\Places\PlaceModels;
use Modules\Shared\Places\PlaceStatus;
use Modules\Trips\Models\Trip;

/** Places waiting for review, every kind, oldest first. Fed by organisers from slice 3.2. */
final class ReviewQueue extends Page
{
    protected string $view = 'admin::review-queue';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $title = 'Review queue';

    public static function getNavigationBadge(): ?string
    {
        $count = collect(PlaceModels::ALL)->sum(fn ($model) => $model::query()->where('status', PlaceStatus::PENDING)->count());

        return $count > 0 ? (string) $count : null;
    }

    /** @return list<array{kind: string, id: string, name: string, createdAt: string, waitingTrip: ?string}> */
    public function getPending(): array
    {
        return collect(PlaceModels::ALL)
            ->flatMap(fn (string $model, string $kind) => $model::query()
                ->where('status', PlaceStatus::PENDING)
                ->get()
                ->map(fn ($place) => [
                    'kind' => $kind,
                    'id' => $place->getKey(),
                    'name' => collect($place->name)->filter()->first() ?? $place->getKey(),
                    'createdAt' => $place->created_at->toIso8601String(),
                    'waitingTrip' => Trip::query()->where('status', 'draft')
                        ->where('destination_kind', $kind)->where('destination_id', $place->getKey())
                        ->value('title'),
                ]))
            ->sortBy('createdAt')
            ->values()
            ->all();
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->color('success')
            ->requiresConfirmation()
            ->action(fn (array $arguments) => app(Moderate::class)->approve($arguments['kind'], $arguments['id']));
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->color('danger')
            ->schema([Textarea::make('reason')->required()->maxLength(500)])
            ->action(fn (array $data, array $arguments) => app(Moderate::class)->reject($arguments['kind'], $arguments['id'], $data['reason']));
    }
}
