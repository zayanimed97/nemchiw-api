<?php

namespace Modules\Media\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Contracts\ChatPhotos;
use Modules\Media\Images\Reencoder;

final class ChatPhotoStore implements ChatPhotos
{
    public function __construct(private readonly Reencoder $reencoder) {}

    public function store(string $folder, string $bytes): string
    {
        $large = $this->reencoder->toWebp($bytes, 1280);
        $small = $this->reencoder->toWebp($bytes, 480);
        $hash = substr(hash('sha256', $large), 0, 32);
        $this->disk()->put($this->file($folder, $hash, 1280), $large);
        $this->disk()->put($this->file($folder, $hash, 480), $small);

        return $hash;
    }

    public function path(string $folder, string $hash, int $size): ?string
    {
        if (! in_array($size, self::SIZES, true) || ! preg_match('/^[0-9a-f]{32}$/', $hash)) {
            return null;
        }
        $file = $this->file($folder, $hash, $size);

        return $this->disk()->exists($file) ? $this->disk()->path($file) : null;
    }

    public function delete(string $folder, string $hash): void
    {
        foreach (self::SIZES as $size) {
            $this->disk()->delete($this->file($folder, $hash, $size));
        }
    }

    public function deleteFolder(string $folder): void
    {
        $this->disk()->deleteDirectory("chat/{$folder}");
    }

    private function file(string $folder, string $hash, int $size): string
    {
        return "chat/{$folder}/{$hash}-{$size}.webp";
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('media.chat_disk'));
    }
}
