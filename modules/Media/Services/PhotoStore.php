<?php

namespace Modules\Media\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Identity\Contracts\ProfilePhotos;
use Modules\Media\Contracts\Photos;
use Modules\Media\Images\Reencoder;
use Modules\Media\Models\Photo;

final class PhotoStore implements Photos, ProfilePhotos
{
    public function __construct(private readonly Reencoder $reencoder) {}

    /** Saves a clean JPEG as the person's photo, replacing (and deleting) the previous one. */
    public function replace(string $userId, string $jpeg): Photo
    {
        $photo = new Photo;
        $photo->id = $photo->newUniqueId();
        $path = $photo->id.'.jpg';
        $this->disk()->put($path, $jpeg);

        try {
            $previous = DB::transaction(function () use ($photo, $userId, $path) {
                $previous = Photo::query()->where('user_id', $userId)->lockForUpdate()->first();
                $previous?->delete();
                $photo->forceFill(['user_id' => $userId, 'path' => $path, 'status' => 'unverified'])->save();

                return $previous;
            });
        } catch (\Throwable $e) {
            // No row points at the file: remove it, or account deletion could never find it.
            $this->disk()->delete($path);

            throw $e;
        }

        if ($previous !== null) {
            $this->disk()->delete($previous->path);
        }

        return $photo;
    }

    public function forget(string $userId): void
    {
        $photo = Photo::query()->where('user_id', $userId)->first();
        if ($photo !== null) {
            $photo->delete();
            $this->disk()->delete($photo->path);
        }
    }

    public function current(string $userId): ?array
    {
        $photo = Photo::query()->where('user_id', $userId)->first();

        return $photo === null ? null : ['id' => $photo->id, 'status' => $photo->status];
    }

    public function portrait(string $photoId): string
    {
        return $this->reencoder->toJpeg((string) $this->disk()->get(Photo::query()->findOrFail($photoId)->path), 1024);
    }

    public function setStatus(string $photoId, string $status): void
    {
        if (! in_array($status, Photo::STATUSES, true)) {
            throw new InvalidArgumentException("Unknown photo status {$status}");
        }
        Photo::query()->whereKey($photoId)->update(['status' => $status]);
    }

    public function ownerView(string $userId): ?array
    {
        $photo = Photo::query()->where('user_id', $userId)->first();

        return $photo === null ? null : ['url' => $this->url($photo), 'status' => $photo->status];
    }

    public function publicView(string $userId): ?array
    {
        $photo = Photo::query()->where('user_id', $userId)->where('status', 'verified')->first();

        return $photo === null ? null : ['url' => $this->url($photo), 'verified' => true];
    }

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('media.disk'));
    }

    private function url(Photo $photo): string
    {
        return url("/api/v1/photos/{$photo->id}");
    }
}
