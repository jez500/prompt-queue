<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Writes a consistent copy of the SQLite database.
 *
 * Copying the file while the app is running can capture a half-written
 * transaction, and misses the -wal sidecar entirely if write-ahead logging is
 * ever enabled. VACUUM INTO takes a proper snapshot through the database
 * engine, so the result opens cleanly even under load.
 */
class BackupCommand extends Command
{
    /** @var string */
    protected $signature = 'pq:backup
        {path : Where to write the backup file}
        {--database= : The connection to back up, defaulting to the configured one}
        {--force : Overwrite the file if it already exists}';

    /** @var string */
    protected $description = 'Write a consistent copy of the database to a file';

    public function handle(): int
    {
        $connection = DB::connection($this->option('database'));

        if ($connection->getDriverName() !== 'sqlite') {
            $this->components->error(
                'pq:backup only supports SQLite. Use your database\'s own dump tool instead.'
            );

            return self::FAILURE;
        }

        /* VACUUM cannot run inside a transaction, and SQLite's own error for
           it does not say what to do about it. */
        if ($connection->transactionLevel() > 0) {
            $this->components->error(
                'A database transaction is open. Run pq:backup on its own, not from inside one.'
            );

            return self::FAILURE;
        }

        $path = (string) $this->argument('path');

        if (file_exists($path) && ! $this->option('force')) {
            $this->components->error("[{$path}] already exists. Pass --force to overwrite it.");

            return self::FAILURE;
        }

        /* VACUUM INTO refuses to write to a file that already exists. */
        if (file_exists($path) && ! @unlink($path)) {
            $this->components->error("[{$path}] could not be replaced.");

            return self::FAILURE;
        }

        try {
            $connection->statement('VACUUM INTO ?', [$path]);
        } catch (Throwable $exception) {
            $this->components->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(
            sprintf('Backup written to [%s] (%s).', $path, $this->humanSize((int) filesize($path)))
        );

        return self::SUCCESS;
    }

    /**
     * Format a byte count for the confirmation line.
     */
    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }
}
