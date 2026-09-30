<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/** A fake mysqldump that writes a small dump where --result-file points. */
function fakeMysqldump(): void
{
    Process::fake(function (PendingProcess $process) {
        $target = collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--result-file='));
        file_put_contents(substr($target, strlen('--result-file=')), '-- dump');

        return Process::result();
    });
}

beforeEach(function () {
    config([
        'database.connections.mysql' => array_merge(config('database.connections.mysql'), [
            'host' => 'localhost', 'port' => '3306', 'database' => 'nemchiw', 'username' => 'nem', 'password' => 's3cret',
        ]),
    ]);
    $this->dir = storage_path('app/private/backups');
    File::deleteDirectory($this->dir);
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('dumps with the password in the environment, not the command line', function () {
    fakeMysqldump();

    $this->artisan('backup:database', ['--connection' => 'mysql'])->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process) => in_array('--single-transaction', $process->command, true)
        && ! str_contains(implode(' ', $process->command), 's3cret')
        && ($process->environment['MYSQL_PWD'] ?? null) === 's3cret');
    expect(File::glob($this->dir.'/*.sql.gz'))->toHaveCount(1);
    expect(File::glob($this->dir.'/*.sql'))->toBe([]);
});

it('keeps the 14 newest backups', function () {
    File::ensureDirectoryExists($this->dir);
    foreach (range(1, 16) as $i) {
        touch($this->dir.sprintf('/nemchiw-2026-09-%02d.sql.gz', $i), strtotime("2026-09-$i"));
    }
    fakeMysqldump();

    $this->artisan('backup:database', ['--connection' => 'mysql'])->assertSuccessful();

    $files = File::glob($this->dir.'/*.sql.gz');
    expect($files)->toHaveCount(14);
    expect(collect($files)->contains(fn ($f) => str_contains($f, '2026-09-01')))->toBeFalse();
});

it('fails loudly when mysqldump fails', function () {
    Process::fake(fn () => Process::result(exitCode: 2));

    $this->artisan('backup:database', ['--connection' => 'mysql'])->assertFailed();
});
