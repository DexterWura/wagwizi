<?php

namespace App\Services\Admin;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrationService
{
    private Migrator $migrator;
    private string $migrationPath;

    public function __construct()
    {
        $this->migrator = app('migrator');
        $this->migrationPath = database_path('migrations');
    }

    public function getAllMigrations(): array
    {
        $this->migrator->requireFiles(
            $files = $this->migrator->getMigrationFiles($this->migrationPath)
        );

        $ranMigrations = DB::table('migrations')
            ->orderBy('batch')
            ->orderBy('migration')
            ->get()
            ->keyBy('migration');

        $result = [];

        foreach ($files as $name => $path) {
            $ran = $ranMigrations->get($name);
            $result[] = [
                'name'  => $name,
                'path'  => $path,
                'ran'   => $ran !== null,
                'batch' => $ran?->batch,
            ];
        }

        return $result;
    }

    /**
     * @return array{count: int, names: list<string>}|null  Null when none pending or DB unavailable.
     */
    public function getPendingMigrationsSummary(): ?array
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return null;
            }

            $all = $this->getAllMigrations();
        } catch (\Throwable) {
            return null;
        }

        $pending = array_values(array_filter($all, static fn (array $m): bool => ! $m['ran']));
        if ($pending === []) {
            return null;
        }

        $names = array_values(array_column($pending, 'name'));
        sort($names);

        return [
            'count' => count($names),
            'names' => $names,
        ];
    }

    public function runAll(): array
    {
        return $this->migrator->run($this->migrationPath);
    }

    public function runSingle(string $migrationName): bool
    {
        $files = $this->migrator->getMigrationFiles($this->migrationPath);

        if (!isset($files[$migrationName])) {
            throw new \RuntimeException("Migration not found: {$migrationName}");
        }

        $this->migrator->requireFiles([$migrationName => $files[$migrationName]]);

        $migration = $this->migrator->resolve($migrationName);
        $migration->up();

        $batch = DB::table('migrations')->max('batch') ?? 0;
        DB::table('migrations')->insert([
            'migration' => $migrationName,
            'batch'     => $batch + 1,
        ]);

        return true;
    }

    public function rollbackBatch(): array
    {
        return $this->migrator->rollback($this->migrationPath);
    }

    public function rollbackSingle(string $migrationName): bool
    {
        $files = $this->migrator->getMigrationFiles($this->migrationPath);

        if (!isset($files[$migrationName])) {
            throw new \RuntimeException("Migration not found: {$migrationName}");
        }

        $ran = DB::table('migrations')->where('migration', $migrationName)->first();
        if (!$ran) {
            throw new \RuntimeException("Migration has not been run: {$migrationName}");
        }

        $this->migrator->requireFiles([$migrationName => $files[$migrationName]]);

        $migration = $this->migrator->resolve($migrationName);
        $migration->down();

        DB::table('migrations')->where('migration', $migrationName)->delete();

        return true;
    }
}
