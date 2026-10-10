<?php

namespace Modules\Shared\Places;

/** Places as other modules may see and add them. Implemented by Admin, which owns the place models' editing. */
interface PlaceDirectory
{
    /** @return array{kind: string, id: string, name: array<string, string>, governorate: string, latitude: float, longitude: float, status: string, cover: ?string, createdBy: ?string}|null */
    public function find(string $kind, string $id): ?array;

    /**
     * Creates a spot or a trail from an organiser's form, live or pending review.
     *
     * @param  array<string, mixed>  $fields  validated by the caller
     * @return string the new place id
     */
    public function submit(string $kind, array $fields, string $userId, bool $live): string;
}
