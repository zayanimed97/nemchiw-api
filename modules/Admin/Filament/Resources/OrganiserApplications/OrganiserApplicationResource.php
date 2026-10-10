<?php

namespace Modules\Admin\Filament\Resources\OrganiserApplications;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Admin\Models\AdminAction;
use Modules\Organisers\Actions\DecideApplication;
use Modules\Organisers\Models\OrganiserApplication;

final class OrganiserApplicationResource extends Resource
{
    protected static ?string $model = OrganiserApplication::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Organiser applications';

    public static function getNavigationBadge(): ?string
    {
        $count = OrganiserApplication::query()->where('status', 'submitted')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('user_id')->label('User')->copyable(),
                TextColumn::make('regions')->badge(),
                TextColumn::make('experience_years')->label('Years'),
                TextColumn::make('status')->badge(),
                TextColumn::make('submitted_at')->since()->sortable(),
            ])
            ->defaultSort('submitted_at')
            ->filters([SelectFilter::make('status')->options(array_combine(
                ['draft', 'submitted', 'approved', 'rejected', 'revoked'],
                ['draft', 'submitted', 'approved', 'rejected', 'revoked'],
            ))->default('submitted')])
            ->recordActions([
                Action::make('documents')
                    ->modalHeading('Documents')
                    ->modalContent(fn (OrganiserApplication $record) => view('admin::organiser-documents', ['documents' => $record->documents]))
                    ->modalSubmitAction(false),
                Action::make('approve')->color('success')->requiresConfirmation()
                    ->visible(fn (OrganiserApplication $record) => $record->status === 'submitted')
                    ->action(function (OrganiserApplication $record) {
                        app(DecideApplication::class)->approve($record);
                        self::log('approved', $record);
                    }),
                Action::make('reject')->color('danger')
                    ->visible(fn (OrganiserApplication $record) => $record->status === 'submitted')
                    ->schema([Textarea::make('reason')->required()->maxLength(500)])
                    ->action(function (OrganiserApplication $record, array $data) {
                        app(DecideApplication::class)->reject($record, $data['reason']);
                        self::log('rejected', $record);
                    }),
                Action::make('revoke')->color('danger')
                    ->visible(fn (OrganiserApplication $record) => $record->status === 'approved')
                    ->schema([Textarea::make('reason')->required()->maxLength(500)])
                    ->action(function (OrganiserApplication $record, array $data) {
                        app(DecideApplication::class)->revoke($record, $data['reason']);
                        self::log('revoked', $record);
                    }),
            ]);
    }

    private static function log(string $action, OrganiserApplication $record): void
    {
        AdminAction::query()->create([
            'admin_id' => auth('admin')->id(), 'action' => $action,
            'target_kind' => 'org_application', 'target_id' => $record->id, 'changes' => null,
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOrganiserApplications::route('/')];
    }
}
