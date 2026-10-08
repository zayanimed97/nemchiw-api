<?php

namespace Modules\Media\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Media\Contracts\PlacePhotos;
use Modules\Media\Events\PlacePhotosChanged;
use Modules\Media\Images\Reencoder;
use Modules\Media\Models\PlacePhoto;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

final class PlacePhotoStore implements PlacePhotos
{
    private const SIZES = [480, 1280];

    public function __construct(private readonly Reencoder $reencoder) {}

    public function hashesFor(string $kind, array $placeIds): array
    {
        if ($placeIds === []) {
            return [];
        }

        return PlacePhoto::query()
            ->where('kind', $kind)
            ->whereIn('place_id', $placeIds)
            ->orderBy('position')
            ->get(['place_id', 'hash'])
            ->groupBy('place_id')
            ->map(fn ($rows) => $rows->pluck('hash')->all())
            ->all();
    }

    public function add(string $kind, string $placeId, string $bytes): string
    {
        $large = $this->reencoder->toWebp($bytes, 1280);
        $small = $this->reencoder->toWebp($bytes, 480);
        $hash = substr(hash('sha256', $large), 0, 32);
        $this->disk()->put("{$hash}-1280.webp", $large);
        $this->disk()->put("{$hash}-480.webp", $small);

        try {
            DB::transaction(function () use ($kind, $placeId, $hash) {
                $rows = PlacePhoto::query()->where('kind', $kind)->where('place_id', $placeId)->lockForUpdate()->get();
                if ($rows->contains('hash', $hash)) {
                    return;
                }
                if ($rows->count() >= self::MAX) {
                    throw new ApiException(ApiErrorCode::Validation, 'A place has at most '.self::MAX.' photos');
                }
                PlacePhoto::query()->create([
                    'kind' => $kind,
                    'place_id' => $placeId,
                    'hash' => $hash,
                    'position' => ((int) $rows->max('position')) + ($rows->isEmpty() ? 0 : 1),
                ]);
            });
        } catch (\Throwable $e) {
            $this->deleteIfUnused($hash);

            throw $e;
        }

        PlacePhotosChanged::dispatch($kind, $placeId);

        return $hash;
    }

    public function remove(string $kind, string $placeId, string $hash): void
    {
        PlacePhoto::query()->where(compact('kind', 'hash'))->where('place_id', $placeId)->delete();
        $this->deleteIfUnused($hash);
        PlacePhotosChanged::dispatch($kind, $placeId);
    }

    public function reorder(string $kind, string $placeId, array $hashes): void
    {
        DB::transaction(function () use ($kind, $placeId, $hashes) {
            $rows = PlacePhoto::query()->where('kind', $kind)->where('place_id', $placeId)->lockForUpdate()->get()->keyBy('hash');
            $same = count($hashes) === $rows->count() && collect($hashes)->every(fn ($h) => $rows->has($h));
            if (! $same) {
                throw new InvalidArgumentException('A reorder must list the same photos');
            }
            foreach (array_values($hashes) as $position => $hash) {
                $rows[$hash]->update(['position' => $position]);
            }
        });
        PlacePhotosChanged::dispatch($kind, $placeId);
    }

    public function forgetPlace(string $kind, string $placeId): void
    {
        $hashes = PlacePhoto::query()->where('kind', $kind)->where('place_id', $placeId)->pluck('hash');
        PlacePhoto::query()->where('kind', $kind)->where('place_id', $placeId)->delete();
        $hashes->each(fn (string $hash) => $this->deleteIfUnused($hash));
    }

    public function url(string $hash, int $size): string
    {
        return url("/media/places/{$hash}-{$size}.webp");
    }

    private function deleteIfUnused(string $hash): void
    {
        if (! PlacePhoto::query()->where('hash', $hash)->exists()) {
            foreach (self::SIZES as $size) {
                $this->disk()->delete("{$hash}-{$size}.webp");
            }
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk('place_photos');
    }
}
