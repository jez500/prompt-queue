<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| pq:backup
|--------------------------------------------------------------------------
|
| VACUUM INTO cannot run inside a transaction, and the suite wraps every test
| in one via RefreshDatabase. These therefore work on a temporary file
| database of their own, reached through --database, which also makes the
| test meaningful: backing up :memory: would prove very little.
|
*/

function backupDir(): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'pq-backup-test';
}

function sourcePath(): string
{
    return backupDir().'/source.sqlite';
}

beforeEach(function (): void {
    @mkdir(backupDir(), 0777, true);
    touch(sourcePath());

    config(['database.connections.backup_source' => [
        'driver' => 'sqlite',
        'database' => sourcePath(),
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    Artisan::call('migrate', ['--database' => 'backup_source', '--force' => true]);
});

afterEach(function (): void {
    DB::purge('backup_source');
    DB::purge('backup_copy');

    foreach (glob(backupDir().'/*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir(backupDir());
});

it('writes a backup that opens and holds the data', function (): void {
    DB::connection('backup_source')->table('users')->insert([
        'name' => 'Jez',
        'email' => 'jez@example.com',
        'password' => 'hashed',
    ]);

    $path = backupDir().'/backup.sqlite';

    $this->artisan('pq:backup', ['path' => $path, '--database' => 'backup_source'])
        ->assertSuccessful();

    expect($path)->toBeFile();

    config(['database.connections.backup_copy' => [
        'driver' => 'sqlite',
        'database' => $path,
        'prefix' => '',
    ]]);

    expect(DB::connection('backup_copy')->table('users')->pluck('email')->all())
        ->toBe(['jez@example.com']);
});

it('refuses to overwrite an existing file unless forced', function (): void {
    $path = backupDir().'/backup.sqlite';
    file_put_contents($path, 'not a database');

    $this->artisan('pq:backup', ['path' => $path, '--database' => 'backup_source'])
        ->assertFailed();

    expect(file_get_contents($path))->toBe('not a database');

    $this->artisan('pq:backup', [
        'path' => $path,
        '--database' => 'backup_source',
        '--force' => true,
    ])->assertSuccessful();

    expect(file_get_contents($path))->not->toBe('not a database');
});

it('fails clearly when the path cannot be written', function (): void {
    $this->artisan('pq:backup', [
        'path' => '/nonexistent-directory/backup.sqlite',
        '--database' => 'backup_source',
    ])->assertFailed();
});

it('refuses to run inside a transaction rather than reporting a raw sqlite error', function (): void {
    DB::connection('backup_source')->beginTransaction();

    $this->artisan('pq:backup', [
        'path' => backupDir().'/backup.sqlite',
        '--database' => 'backup_source',
    ])
        ->expectsOutputToContain('A database transaction is open.')
        ->assertFailed();

    DB::connection('backup_source')->rollBack();
});
