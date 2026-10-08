<?php

namespace Modules\Media\Contracts;

/** Public photos of places. A place is (kind, id); kinds are Shared\Places\PlaceKind. */
interface PlacePhotos
{
    public const MAX = 6;

    /** @param list<string> $placeIds @return array<string, list<string>> place id → hashes, cover first */
    public function hashesFor(string $kind, array $placeIds): array;

    /** Re-encodes and appends the photo; returns its hash. Same image twice is a no-op. */
    public function add(string $kind, string $placeId, string $bytes): string;

    public function remove(string $kind, string $placeId, string $hash): void;

    /** @param list<string> $hashes the place's hashes in their new order (same set) */
    public function reorder(string $kind, string $placeId, array $hashes): void;

    /** Removes every photo of the place (when it is pruned). */
    public function forgetPlace(string $kind, string $placeId): void;

    /** @param 480|1280 $size */
    public function url(string $hash, int $size): string;
}
