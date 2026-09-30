<?php

namespace Modules\Shared\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/** Nightly gzipped mysqldump outside the web root. Keeps the newest 14. */
final class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--connection= : Database connection (default: the app default)}';

    protected $description = 'Dump the MySQL database to storage/app/private/backups';

    private const KEEP = 14;

    public function handle(): int
    {
        $db = config('database.connections.'.($this->option('connection') ?: config('database.default')));
        $dir = storage_path('app/private/backups');
        File::ensureDirectoryExists($dir, 0700);

        $sql = $dir.'/nemchiw-'.now()->format('Y-m-d-His').'.sql';
        // The password travels in the environment: a command-line argument would
        // show up in the server's process list.
        $result = Process::env(['MYSQL_PWD' => (string) $db['password']])
            ->timeout(600)
            ->run([
                'mysqldump', '--single-transaction', '--quick', '--no-tablespaces',
                '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
                '--result-file='.$sql, $db['database'],
            ]);

        if ($result->failed() || ! is_file($sql)) {
            File::delete($sql);
            $this->error('mysqldump failed');

            return self::FAILURE;
        }

        $this->gzip($sql);
        $this->prune($dir);

        return self::SUCCESS;
    }

    private function gzip(string $path): void
    {
        $in = fopen($path, 'rb');
        $out = gzopen($path.'.gz', 'wb9');
        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
        unlink($path);
        chmod($path.'.gz', 0600);
    }

    private function prune(string $dir): void
    {
        collect(File::glob($dir.'/*.sql.gz'))
            ->sortByDesc(fn (string $file) => filemtime($file))
            ->slice(self::KEEP)
            ->each(fn (string $file) => unlink($file));
    }
}
