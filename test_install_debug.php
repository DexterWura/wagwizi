<?php

declare(strict_types=1);

/**
 * Instrumented replay of install/index.php (session 6ca688).
 * Plain-text response includes steps + full debug-6ca688.log contents for remote diagnosis.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: text/plain; charset=utf-8');

$GLOBALS['agent_log_run_id'] = 'test-install-web';

require_once __DIR__ . '/debug_6ca688_inc.php';

function out(string $line): void
{
    echo $line . PHP_EOL;
}

out('=== test_install_debug.php (session 6ca688) ===');

// #region agent log
agent_log_6ca688('test_install_debug.php:start', 'script entry', [], 'H2');
// #endregion

$root = __DIR__;
$logicPath = $root . '/app/logic';

out('[A] project root: ' . $root);

try {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    out('[B] session_start OK status=' . session_status());
    agent_log_6ca688('test_install_debug.php:session', 'session ok', ['status' => session_status()], 'H1');
} catch (Throwable $e) {
    out('[B] FAIL: ' . $e->getMessage());
    agent_log_6ca688('test_install_debug.php:session', 'fail', ['msg' => $e->getMessage()], 'H1');
    throw $e;
}

require $logicPath . '/vendor/autoload.php';
out('[C] vendor/autoload OK');

$app = require_once $logicPath . '/bootstrap.php';
out('[D] bootstrap.php OK base=' . $app->basePath());

try {
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    out('[E] Console\\Kernel::bootstrap OK cache=' . config('cache.default') . ' session_drv=' . config('session.driver'));
    agent_log_6ca688('test_install_debug.php:console', 'bootstrap ok', [
        'cache' => config('cache.default'),
        'session_driver' => config('session.driver'),
    ], 'H2');
} catch (Throwable $e) {
    out('[E] FAIL: ' . $e::class . ' ' . $e->getMessage());
    out($e->getTraceAsString());
    agent_log_6ca688('test_install_debug.php:console', 'fail', ['class' => $e::class, 'msg' => $e->getMessage()], 'H2');
    throw $e;
}

try {
    $installer = new \App\Services\Installer\InstallerService();
    $checks = $installer->checkRequirements();
    $passed = $installer->requirementsPassed($checks);
    out('[F] InstallerService::checkRequirements OK passed=' . ($passed ? '1' : '0'));
    agent_log_6ca688('test_install_debug.php:checks', 'ok', ['passed' => $passed], 'H5');
} catch (Throwable $e) {
    out('[F] FAIL: ' . $e->getMessage());
    agent_log_6ca688('test_install_debug.php:checks', 'fail', ['msg' => $e->getMessage()], 'H5');
    throw $e;
}

try {
    $html = view('install.requirements', [
        'checks' => $checks,
        'passed' => $passed,
        'csrfToken' => 'test',
        'old' => [],
    ])->render();
    out('[G] view(install.requirements) OK bytes=' . strlen($html));
    agent_log_6ca688('test_install_debug.php:view', 'render ok', ['len' => strlen($html)], 'H3');
} catch (Throwable $e) {
    out('[G] FAIL: ' . $e::class . ' ' . $e->getMessage());
    out($e->getTraceAsString());
    agent_log_6ca688('test_install_debug.php:view', 'fail', ['class' => $e::class, 'msg' => $e->getMessage()], 'H3');
}

$logPath = $root . '/debug-6ca688.log';
out('');
out('--- debug-6ca688.log (full dump) ---');
if (is_readable($logPath)) {
    out(file_get_contents($logPath) ?: '(empty)');
} else {
    out('(not readable or missing: ' . $logPath . ')');
}

out('');
out('=== end test_install_debug ===');
