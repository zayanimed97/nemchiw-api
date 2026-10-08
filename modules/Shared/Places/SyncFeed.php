<?php

namespace Modules\Shared\Places;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Shared\Support\Iso;

/**
 * The sync answer every place feed gives: { data, removed, full, serverTime }.
 *
 * - No cursor, or one older than the tombstone window: `full` is true and `data` is every
 *   live place (not paged; the client replaces its table).
 * - Otherwise every row changed after the cursor, paged: live ones in `data`, the rest
 *   (hidden, rejected, pending, deleted) as ids in `removed`.
 */
final class SyncFeed
{
    /**
     * @param  Builder<Model>  $query  the place model including trashed rows
     * @param  callable(Collection<int, Model>): list<array<string, mixed>>  $present
     */
    public static function respond(Request $request, Builder $query, callable $present): JsonResponse
    {
        $validated = $request->validate(['updatedSince' => ['sometimes', 'date']]);
        $now = CarbonImmutable::now();
        $lagged = $now->subSeconds((int) config('shared.places.cursor_lag_seconds'));
        $since = isset($validated['updatedSince'])
            ? CarbonImmutable::parse($validated['updatedSince'])->utc()
            : null;

        if ($since === null || $since->lt($now->subDays((int) config('shared.places.tombstone_days')))) {
            $rows = $query->where('status', PlaceStatus::LIVE)->whereNull('deleted_at')->orderBy('id')->get();

            return self::json($present($rows), [], true, $lagged);
        }

        $pageSize = (int) config('shared.places.page_size');
        $rows = $query
            // Bound as a string: the default binding format drops milliseconds.
            ->where('updated_at', '>', $since->format('Y-m-d H:i:s.v'))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($pageSize + 1)
            ->get();
        $page = $rows->take($pageSize);

        // A partial page hands back a cursor a little in the past: a write that took its
        // timestamp before this read but committed after it is sent next time instead of
        // skipped. Clients upsert by id, so the overlap costs nothing.
        $cursor = $rows->count() > $pageSize ? $page->last()->getAttribute('updated_at') : $lagged;

        [$live, $gone] = $page->partition(
            fn (Model $row) => $row->getAttribute('status') === PlaceStatus::LIVE && $row->getAttribute('deleted_at') === null,
        );

        return self::json($present($live->values()), $gone->modelKeys(), false, $cursor);
    }

    /** @param list<array<string, mixed>> $data @param list<string> $removed */
    private static function json(array $data, array $removed, bool $full, \DateTimeInterface $cursor): JsonResponse
    {
        return new JsonResponse([
            'data' => $data,
            'removed' => array_values($removed),
            'full' => $full,
            'serverTime' => Iso::format($cursor),
        ]);
    }
}
