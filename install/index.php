<?php

declare(strict_types=1);

/**
 * Standalone installer — served at /install/* via root .htaccess.
 * Bypasses the main index.php HTTP kernel (sessions, middleware, etc.).
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('PROJECT_ROOT', dirname(__DIR__));
define('LOGIC_PATH', PROJECT_ROOT . '/app/logic');

require LOGIC_PATH . '/vendor/autoload.php';

$app = require_once LOGIC_PATH . '/bootstrap.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = isset($_GET['__path__']) ? (string) $_GET['__path__'] : '';

\App\Services\Installer\StandaloneInstaller::run($path);
