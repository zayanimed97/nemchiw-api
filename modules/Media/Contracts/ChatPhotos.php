<?php

namespace Modules\Media\Contracts;

/**
 * Private photos in a conversation: re-encoded WebP 480 and 1280, kept off the public web.
 * The caller decides who may read them; this only stores and finds files.
 */
interface ChatPhotos
{
    public const SIZES = [480, 1280];

    /** Re-encodes (EXIF and GPS dropped) and stores both sizes; returns the hash. */
    public function store(string $folder, string $bytes): string;

    /** Absolute path of a stored size, or null. */
    public function path(string $folder, string $hash, int $size): ?string;

    public function delete(string $folder, string $hash): void;

    public function deleteFolder(string $folder): void;
}
