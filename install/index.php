<?php

declare(strict_types=1);

/**
 * Standalone installer — served at /install/* via root .htaccess.
 * Bypasses the main index.php HTTP kernel (sessions, middleware, etc.).
 */

// #region agent log
require_once dirname(__DIR__) . '/debug_6ca688_inc.php';
agent_log_6ca688('install/index.php:1', 'entry', ['get' => array_keys($_GET), 'session_status' => session_status()], 'H1');
// #endregion

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// #region agent log
agent_log_6ca688('install/index.php:session', 'after session_start', ['session_status' => session_status()], 'H1');
// #endregion

define('PROJECT_ROOT', dirname(__DIR__));
define('LOGIC_PATH', PROJECT_ROOT . '/app/logic');

require LOGIC_PATH . '/vendor/autoload.php';

// #region agent log
agent_log_6ca688('install/index.php:autoload', 'vendor loaded', [], 'H2');
// #endregion

$app = require_once LOGIC_PATH . '/bootstrap.php';

// #region agent log
agent_log_6ca688('install/index.php:bootstrap_php', 'Application created', ['base_path' => $app->basePath()], 'H2');
// #endregion

try {
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    // #region agent log
    agent_log_6ca688('install/index.php:console_bootstrap', 'Console Kernel bootstrap OK', [
        'cache_default' => config('cache.default'),
        'session_driver' => config('session.driver'),
    ], 'H2');
    // #endregion
} catch (Throwable $e) {
    // #region agent log
    agent_log_6ca688('install/index.php:console_bootstrap', 'FAIL', [
        'class' => $e::class,
        'msg' => $e->getMessage(),
    ], 'H2');
    // #endregion
    throw $e;
}

$path = isset($_GET['__path__']) ? (string) $_GET['__path__'] : '';

// #region agent log
agent_log_6ca688('install/index.php:pre_run', 'calling StandaloneInstaller', ['path' => $path], 'H5');
// #endregion

\App\Services\Installer\StandaloneInstaller::run($path);
