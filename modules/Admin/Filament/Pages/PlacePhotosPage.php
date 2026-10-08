<?php

namespace Modules\Admin\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Modules\Admin\Models\AdminAction;
use Modules\Admin\Places\PlaceModels;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Shared\Errors\ApiException;

/** Up to 6 photos per place, first = cover. Each resource extends this with its own $resource. */
abstract class PlacePhotosPage extends Page
{
    use InteractsWithRecord;

    protected string $view = 'admin::place-photos';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Photos: '.($this->record->name['en'] ?? $this->record->getKey());
    }

    /** @return list<array{hash: string, url: string}> */
    public function photos(): array
    {
        $hashes = app(PlacePhotos::class)->hashesFor($this->kind(), [$this->record->getKey()])[$this->record->getKey()] ?? [];

        return array_map(fn ($hash) => ['hash' => $hash, 'url' => app(PlacePhotos::class)->url($hash, 480)], $hashes);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Add photo')
                ->disabled(fn () => count($this->photos()) >= PlacePhotos::MAX)
                ->schema([FileUpload::make('photo')->image()->required()->maxSize(10240)->disk('local')->directory('admin-uploads')])
                ->action(function (array $data) {
                    $bytes = (string) Storage::disk('local')->get($data['photo']);
                    Storage::disk('local')->delete($data['photo']);
                    try {
                        app(PlacePhotos::class)->add($this->kind(), $this->record->getKey(), $bytes);
                        $this->log('photo added');
                    } catch (ApiException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }

    public function remove(string $hash): void
    {
        app(PlacePhotos::class)->remove($this->kind(), $this->record->getKey(), $hash);
        $this->log('photo removed');
    }

    public function move(string $hash, int $offset): void
    {
        $hashes = array_column($this->photos(), 'hash');
        $from = array_search($hash, $hashes, true);
        $to = $from + $offset;
        if ($from === false || $to < 0 || $to >= count($hashes)) {
            return;
        }
        [$hashes[$from], $hashes[$to]] = [$hashes[$to], $hashes[$from]];
        app(PlacePhotos::class)->reorder($this->kind(), $this->record->getKey(), $hashes);
        $this->log('photos reordered');
    }

    private function kind(): string
    {
        return PlaceModels::kindOf($this->record);
    }

    private function log(string $what): void
    {
        AdminAction::query()->create([
            'admin_id' => auth('admin')->id(),
            'action' => 'photos',
            'target_kind' => $this->kind(),
            'target_id' => $this->record->getKey(),
            'changes' => ['photos' => $what],
        ]);
    }
}
