<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InstallerService
{
    private string $secretsPath;
    private string $envPath;

    public function __construct()
    {
        $root             = dirname(base_path(), 2);
        $this->secretsPath = $root . DIRECTORY_SEPARATOR . 'secrets';
        $this->envPath     = $this->secretsPath . DIRECTORY_SEPARATOR . '.env';
    }

    public function isInstalled(): bool
    {
        return file_exists($this->secretsPath . '/installed');
    }

    // ────────────────────────────────────────────────────────
    // Step 1: Requirements
    // ────────────────────────────────────────────────────────

    public function checkRequirements(): array
    {
        return [
            'php_version' => $this->checkPhpVersion(),
            'extensions'  => $this->checkExtensions(),
            'permissions' => $this->checkPermissions(),
        ];
    }

    public function requirementsPassed(array $checks): bool
    {
        if (!$checks['php_version']['passed']) {
            return false;
        }

        foreach ($checks['extensions'] as $ext) {
            if ($ext['required'] && !$ext['loaded']) {
                return false;
            }
        }

        foreach ($checks['permissions'] as $perm) {
            if (!$perm['writable']) {
                return false;
            }
        }

        return true;
    }

    private function checkPhpVersion(): array
    {
        $required = '8.2.0';
        return [
            'required' => $required,
            'current'  => PHP_VERSION,
            'passed'   => version_compare(PHP_VERSION, $required, '>='),
        ];
    }

    private function checkExtensions(): array
    {
        $extensions = [
            ['name' => 'pdo',       'required' => true],
            ['name' => 'pdo_mysql', 'required' => true],
            ['name' => 'mbstring',  'required' => true],
            ['name' => 'openssl',   'required' => true],
            ['name' => 'tokenizer', 'required' => true],
            ['name' => 'json',      'required' => true],
            ['name' => 'curl',      'required' => true],
            ['name' => 'fileinfo',  'required' => true],
            ['name' => 'ctype',     'required' => true],
            ['name' => 'xml',       'required' => true],
            ['name' => 'dom',       'required' => true],
            ['name' => 'bcmath',    'required' => false],
            ['name' => 'gd',        'required' => false],
            ['name' => 'zip',       'required' => false],
            ['name' => 'redis',     'required' => false],
        ];

        return array_map(fn (array $ext) => array_merge($ext, [
            'loaded' => extension_loaded($ext['name']),
        ]), $extensions);
    }

    private function checkPermissions(): array
    {
        $dirs = [
            'storage'           => storage_path(),
            'storage/app'       => storage_path('app'),
            'storage/framework' => storage_path('framework'),
            'storage/logs'      => storage_path('logs'),
            'bootstrap/cache'   => base_path('bootstrap/cache'),
            'secrets'           => $this->secretsPath,
        ];

        $results = [];

        foreach ($dirs as $label => $path) {
            $results[] = [
                'directory' => $label,
                'path'      => $path,
                'writable'  => is_dir($path) && is_writable($path),
            ];
        }

        return $results;
    }

    // ────────────────────────────────────────────────────────
    // Step 2: Database
    // ────────────────────────────────────────────────────────

    public function testDatabaseConnection(array $credentials): array
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                $credentials['host'],
                $credentials['port'],
                $credentials['database'],
            );

            $pdo = new \PDO(
                $dsn,
                $credentials['username'],
                $credentials['password'],
                [\PDO::ATTR_TIMEOUT => 5, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
            );

            $pdo->query('SELECT 1');

            return ['success' => true];
        } catch (\PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function saveDatabaseConfig(array $credentials): void
    {
        $this->setEnvValues([
            'DB_HOST'     => $credentials['host'],
            'DB_PORT'     => $credentials['port'],
            'DB_DATABASE' => $credentials['database'],
            'DB_USERNAME' => $credentials['username'],
            'DB_PASSWORD' => $credentials['password'],
        ]);
    }

    // ────────────────────────────────────────────────────────
    // Step 3: Admin + finalize
    // ────────────────────────────────────────────────────────

    public function runMigrations(): string
    {
        Artisan::call('migrate', ['--force' => true]);
        return trim(Artisan::output());
    }

    public function createAdminUser(string $name, string $email, string $password): void
    {
        DB::table('users')->insert([
            'name'       => $name,
            'email'      => $email,
            'password'   => Hash::make($password),
            'role'       => 'super_admin',
            'status'     => 'active',
            'profile_completed' => true,
            'timezone'   => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function generateAppKey(): void
    {
        if (empty(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }
    }

    public function seedCronTasks(): void
    {
        Artisan::call('cron:seed');
    }

    public function setAppUrl(string $url): void
    {
        $this->setEnvValues(['APP_URL' => rtrim($url, '/')]);
    }

    public function markInstalled(): void
    {
        $data = json_encode([
            'installed_at' => now()->toIso8601String(),
            'php_version'  => PHP_VERSION,
            'app_version'  => '1.0.0',
        ], JSON_PRETTY_PRINT);

        file_put_contents($this->secretsPath . '/installed', $data);
    }

    // ────────────────────────────────────────────────────────
    // .env helpers
    // ────────────────────────────────────────────────────────

    private function setEnvValues(array $values): void
    {
        if (!file_exists($this->envPath)) {
            return;
        }

        $contents = file_get_contents($this->envPath);

        foreach ($values as $key => $value) {
            $line = $this->formatEnvAssignment($key, (string) $value);
            $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, $line, $contents);
            } else {
                $contents .= "\n" . $line;
            }
        }

        file_put_contents($this->envPath, $contents);
    }

    /**
     * Single-quoted .env values are literal (no $ expansion), safe for DB passwords.
     */
    private function formatEnvAssignment(string $key, string $value): string
    {
        if ($value === '') {
            return "{$key}=";
        }

        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $value);

        return "{$key}='{$escaped}'";
    }
}
