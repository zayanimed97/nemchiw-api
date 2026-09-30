<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Actions\PruneExpiredCache;

it('deletes expired rows from the database cache', function () {
    DB::table('cache')->insert([
        ['key' => 'old', 'value' => 'x', 'expiration' => time() - 10],
        ['key' => 'fresh', 'value' => 'x', 'expiration' => time() + 3600],
    ]);

    app(PruneExpiredCache::class)();

    expect(DB::table('cache')->pluck('key')->all())->toBe(['fresh']);
});

it('schedules cache pruning hourly', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'cache:prune-expired');
    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('0 * * * *');
});

it('releases overlap locks within minutes if a job is killed', function () {
    $events = collect(app(Schedule::class)->events());
    $expiry = fn (string $needle) => $events->first(fn ($e) => str_contains((string) $e->command, $needle))->expiresAt;

    expect($expiry('queue:work'))->toBe(10);
    expect($expiry('backup:database'))->toBe(120);
});
