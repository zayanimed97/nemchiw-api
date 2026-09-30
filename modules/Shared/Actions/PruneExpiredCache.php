<?php

namespace Modules\Shared\Actions;

use Illuminate\Support\Facades\DB;

/**
 * The database cache store only deletes an expired row when it is read again.
 * Rate-limit keys for one-off IPs and phones never are, so without this the
 * table grows forever on shared hosting.
 */
final class PruneExpiredCache
{
    public function __invoke(): int
    {
        $table = (string) config('cache.stores.database.table', 'cache');

        return DB::table($table)->where('expiration', '<', time())->delete();
    }
}
