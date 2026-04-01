<?php

declare(strict_types=1);

namespace App\Services\Installer;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Throwable;

/**
 * Runs the web installer from /install/index.php (project root), outside the HTTP kernel.
 */
final class StandaloneInstaller
{
    private const CSRF_KEY = '_installer_csrf';

    private const DB_OK_KEY = 'install_db_ok';

    private const OLD_INPUT_KEY = '_old_input';

    public static function run(string $path): void
    {
        // #region agent log
        $logFile = dirname(base_path(), 2) . DIRECTORY_SEPARATOR . 'debug-6ca688.log';
        @file_put_contents($logFile, json_encode([
            'sessionId' => '6ca688',
            'timestamp' => (int) round(microtime(true) * 1000),
            'location' => 'StandaloneInstaller::run',
            'message' => 'entry',
            'data' => ['path' => $path],
            'hypothesisId' => 'H5',
            'runId' => $GLOBALS['agent_log_run_id'] ?? 'pre-fix',
        ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion
        try {
            (new self(new InstallerService()))->dispatch($path);
        } catch (Throwable $e) {
            // #region agent log
            @file_put_contents($logFile, json_encode([
                'sessionId' => '6ca688',
                'timestamp' => (int) round(microtime(true) * 1000),
                'location' => 'StandaloneInstaller::run',
                'message' => 'caught',
                'data' => ['class' => $e::class, 'msg' => $e->getMessage()],
                'hypothesisId' => 'H3',
                'runId' => $GLOBALS['agent_log_run_id'] ?? 'pre-fix',
            ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            report($e);
            http_response_code(500);
            if (config('app.debug')) {
                echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
            } else {
                echo 'Server error during installation.';
            }
        }
    }

    private function __construct(
        private readonly InstallerService $installer,
    ) {}

    private function dispatch(string $path): void
    {
        $path = trim($path, '/');
        $step = $path === '' ? 'index' : $path;

        if ($this->installer->isInstalled()) {
            if ($step === 'complete') {
                $this->renderComplete();
                return;
            }
            $this->render('install.already-installed', $this->sharedViewData());
            return;
        }

        match ($step) {
            'index' => $this->redirect('/install/requirements'),
            'requirements' => $this->stepRequirements(),
            'database' => $this->stepDatabase(),
            'admin' => $this->stepAdmin(),
            'complete' => $this->redirect('/install/requirements'),
            default => $this->redirect('/install/requirements'),
        };
    }

    /**
     * @return array{csrfToken: string, old: array<string, mixed>}
     */
    private function sharedViewData(): array
    {
        return [
            'csrfToken' => $this->csrfToken(),
            'old' => $_SESSION[self::OLD_INPUT_KEY] ?? [],
        ];
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_KEY];
    }

    private function validateCsrf(): bool
    {
        $token = $_POST['_installer_csrf'] ?? '';

        return is_string($token)
            && isset($_SESSION[self::CSRF_KEY])
            && hash_equals($_SESSION[self::CSRF_KEY], $token);
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location, true, 302);
        exit;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function render(string $view, array $extra = []): void
    {
        // #region agent log
        $logFile = dirname(base_path(), 2) . DIRECTORY_SEPARATOR . 'debug-6ca688.log';
        @file_put_contents($logFile, json_encode([
            'sessionId' => '6ca688',
            'timestamp' => (int) round(microtime(true) * 1000),
            'location' => 'StandaloneInstaller::render',
            'message' => 'before view',
            'data' => ['view' => $view],
            'hypothesisId' => 'H3',
            'runId' => $GLOBALS['agent_log_run_id'] ?? 'pre-fix',
        ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion
        echo view($view, array_merge($this->sharedViewData(), $extra))->render();
    }

    private function stepRequirements(): void
    {
        $checks = $this->installer->checkRequirements();
        $this->render('install.requirements', [
            'checks' => $checks,
            'passed' => $this->installer->requirementsPassed($checks),
        ]);
    }

    private function stepDatabase(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCsrf()) {
                http_response_code(419);
                echo 'Session expired. Refresh the page and try again.';
                return;
            }

            $validator = Validator::make($_POST, [
                'db_host' => 'required|string|max:255',
                'db_port' => 'required|integer|min:1|max:65535',
                'db_database' => 'required|string|max:255',
                'db_username' => 'required|string|max:255',
                'db_password' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                $_SESSION[self::OLD_INPUT_KEY] = $_POST;
                $this->render('install.database', ['errors' => $this->toErrorBag($validator)]);
                return;
            }

            $validated = $validator->validated();
            $credentials = [
                'host' => $validated['db_host'],
                'port' => (string) $validated['db_port'],
                'database' => $validated['db_database'],
                'username' => $validated['db_username'],
                'password' => $validated['db_password'] ?? '',
            ];

            $result = $this->installer->testDatabaseConnection($credentials);
            if (!$result['success']) {
                $_SESSION[self::OLD_INPUT_KEY] = $_POST;
                $bag = new ViewErrorBag();
                $bag->put('default', new \Illuminate\Support\MessageBag([
                    'database' => 'Could not connect: ' . ($result['error'] ?? 'unknown error'),
                ]));
                $this->render('install.database', ['errors' => $bag]);
                return;
            }

            $this->installer->saveDatabaseConfig($credentials);
            unset($_SESSION[self::OLD_INPUT_KEY]);
            $_SESSION[self::DB_OK_KEY] = true;
            $this->redirect('/install/admin');
        }

        unset($_SESSION[self::OLD_INPUT_KEY]);
        $this->render('install.database', ['errors' => new ViewErrorBag()]);
    }

    private function stepAdmin(): void
    {
        if (empty($_SESSION[self::DB_OK_KEY])) {
            $this->redirect('/install/database');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCsrf()) {
                http_response_code(419);
                echo 'Session expired. Refresh the page and try again.';
                return;
            }

            $validator = Validator::make($_POST, [
                'admin_name' => 'required|string|max:255',
                'admin_email' => 'required|email|max:255',
                'admin_password' => 'required|string|min:8|confirmed',
                'app_url' => 'required|url|max:255',
            ]);

            if ($validator->fails()) {
                $_SESSION[self::OLD_INPUT_KEY] = $_POST;
                $this->render('install.admin', [
                    'appUrl' => $this->defaultAppUrl(),
                    'errors' => $this->toErrorBag($validator),
                ]);
                return;
            }

            $validated = $validator->validated();

            try {
                $this->installer->setAppUrl($validated['app_url']);
                $this->installer->generateAppKey();
                $this->installer->runMigrations();
                $this->installer->createAdminUser(
                    $validated['admin_name'],
                    $validated['admin_email'],
                    $validated['admin_password'],
                );
                $this->installer->seedCronTasks();
                $this->installer->markInstalled();
                unset($_SESSION[self::DB_OK_KEY], $_SESSION[self::OLD_INPUT_KEY]);
                $this->redirect('/install/complete');
            } catch (Throwable $e) {
                $_SESSION[self::OLD_INPUT_KEY] = $_POST;
                $bag = new ViewErrorBag();
                $bag->put('default', new \Illuminate\Support\MessageBag([
                    'install' => 'Installation failed: ' . $e->getMessage(),
                ]));
                $this->render('install.admin', [
                    'appUrl' => $this->defaultAppUrl(),
                    'errors' => $bag,
                ]);
            }
            return;
        }

        $this->render('install.admin', [
            'appUrl' => $this->defaultAppUrl(),
            'errors' => new ViewErrorBag(),
        ]);
    }

    private function renderComplete(): void
    {
        $this->render('install.complete', [
            'appUrl' => config('app.url'),
        ]);
    }

    private function defaultAppUrl(): string
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }

    private function toErrorBag(\Illuminate\Contracts\Validation\Validator $validator): ViewErrorBag
    {
        $bag = new ViewErrorBag();
        $bag->put('default', $validator->errors());

        return $bag;
    }
}
