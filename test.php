<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
@set_time_limit(30);

header('Content-Type: text/plain; charset=utf-8');

function line(string $label, string $value = ''): void
{
    echo str_pad($label, 34) . $value . PHP_EOL;
}

function ok(bool $pass): string
{
    return $pass ? 'OK' : 'FAIL';
}

function mask(?string $value, int $show = 2): string
{
    if ($value === null || $value === '') {
        return '(empty)';
    }
    $len = strlen($value);
    if ($len <= ($show * 2)) {
        return str_repeat('*', $len);
    }
    return substr($value, 0, $show) . str_repeat('*', $len - ($show * 2)) . substr($value, -$show);
}

function canWriteDir(string $path): bool
{
    if (!is_dir($path) || !is_writable($path)) {
        return false;
    }
    $probe = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '.__write_probe_' . uniqid('', true);
    $result = @file_put_contents($probe, 'probe');
    if ($result === false) {
        return false;
    }
    @unlink($probe);
    return true;
}

function parseEnvFile(string $envPath): array
{
    if (!is_file($envPath)) {
        return [];
    }

    $rows = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($rows)) {
        return [];
    }

    $vars = [];
    foreach ($rows as $row) {
        $row = trim($row);
        if ($row === '' || str_starts_with($row, '#')) {
            continue;
        }
        $pos = strpos($row, '=');
        if ($pos === false) {
            continue;
        }
        $k = trim(substr($row, 0, $pos));
        $v = trim(substr($row, $pos + 1));
        $v = trim($v, "\"'");
        $vars[$k] = $v;
    }
    return $vars;
}

echo "==== Wagwizi Deployment Diagnostic ====" . PHP_EOL;
echo 'Generated: ' . gmdate('Y-m-d H:i:s') . " UTC" . PHP_EOL;
echo PHP_EOL;

$root = __DIR__;
$logicPath = $root . '/app/logic';
$vendorAutoload = $logicPath . '/vendor/autoload.php';
$bootstrapFile = $logicPath . '/bootstrap.php';
$envPath = $root . '/secrets/.env';
$installedFlag = $root . '/secrets/installed';

line('Root path', $root);
line('Logic path', $logicPath);
echo PHP_EOL;

echo "---- Runtime ----" . PHP_EOL;
line('PHP version', PHP_VERSION);
line('PHP SAPI', PHP_SAPI);
line('Required PHP >= 8.2', ok(version_compare(PHP_VERSION, '8.2.0', '>=')));
line('memory_limit', (string) ini_get('memory_limit'));
line('max_execution_time', (string) ini_get('max_execution_time'));
echo PHP_EOL;

echo "---- Extensions ----" . PHP_EOL;
$requiredExts = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'json', 'curl', 'fileinfo', 'ctype', 'xml', 'dom'];
foreach ($requiredExts as $ext) {
    line("ext:$ext", ok(extension_loaded($ext)));
}
echo PHP_EOL;

echo "---- File Layout ----" . PHP_EOL;
line('index.php exists', ok(is_file($root . '/index.php')));
line('vendor/autoload.php', ok(is_file($vendorAutoload)));
line('bootstrap.php', ok(is_file($bootstrapFile)));
line('secrets/.env', ok(is_file($envPath)));
line('secrets/installed', ok(is_file($installedFlag)));
line('.htaccess exists', ok(is_file($root . '/.htaccess')));
echo PHP_EOL;

echo "---- Permissions ----" . PHP_EOL;
$permChecks = [
    'secrets' => $root . '/secrets',
    'app/logic/storage' => $logicPath . '/storage',
    'app/logic/bootstrap/cache' => $logicPath . '/bootstrap/cache',
];
foreach ($permChecks as $name => $path) {
    line("$name is_dir", ok(is_dir($path)));
    line("$name is_writable", ok(is_writable($path)));
    line("$name write_probe", ok(canWriteDir($path)));
}
echo PHP_EOL;

echo "---- Env Sanity ----" . PHP_EOL;
$env = parseEnvFile($envPath);
$keys = ['APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_KEY', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
foreach ($keys as $k) {
    $v = $env[$k] ?? '(missing)';
    if (in_array($k, ['APP_KEY', 'DB_PASSWORD'], true)) {
        $v = $v === '(missing)' ? $v : mask($v, 3);
    }
    line($k, (string) $v);
}
line('APP_KEY non-empty', ok(!empty($env['APP_KEY'] ?? '')));
echo PHP_EOL;

echo "---- Raw DB Connection Test (PDO) ----" . PHP_EOL;
try {
    $dbHost = $env['DB_HOST'] ?? '';
    $dbPort = $env['DB_PORT'] ?? '3306';
    $dbName = $env['DB_DATABASE'] ?? '';
    $dbUser = $env['DB_USERNAME'] ?? '';
    $dbPass = $env['DB_PASSWORD'] ?? '';

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
        line('DB creds present', 'FAIL (missing host/database/username)');
    } else {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $one = (int) $pdo->query('SELECT 1')->fetchColumn();
        line('PDO connect + SELECT 1', $one === 1 ? 'OK' : 'FAIL');
    }
} catch (Throwable $e) {
    line('PDO connect + SELECT 1', 'FAIL');
    line('PDO error', $e->getMessage());
}
echo PHP_EOL;

echo "---- Laravel Boot Test ----" . PHP_EOL;
try {
    require_once $vendorAutoload;
    line('autoload include', 'OK');
} catch (Throwable $e) {
    line('autoload include', 'FAIL');
    line('autoload error', $e->getMessage());
}

try {
    $app = require $bootstrapFile;
    line('bootstrap include', is_object($app) ? 'OK' : 'FAIL');
} catch (Throwable $e) {
    line('bootstrap include', 'FAIL');
    line('bootstrap error', $e->getMessage());
}

try {
    if (isset($app) && is_object($app)) {
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        line('HTTP kernel resolve', is_object($kernel) ? 'OK' : 'FAIL');
    } else {
        line('HTTP kernel resolve', 'SKIP');
    }
} catch (Throwable $e) {
    line('HTTP kernel resolve', 'FAIL');
    line('kernel error', $e->getMessage());
}
echo PHP_EOL;

echo "---- Installer Expectations ----" . PHP_EOL;
if (!is_file($installedFlag)) {
    line('Fresh install mode', 'YES (should redirect to /install)');
} else {
    line('Fresh install mode', 'NO (already installed flag exists)');
}
echo PHP_EOL;

echo "---- Request Pipeline Test (/install) ----" . PHP_EOL;
try {
    if (isset($app) && is_object($app)) {
        $request = Illuminate\Http\Request::create('/install', 'GET');
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);
        line('Pipeline status code', (string) $response->getStatusCode());
        line('Pipeline content type', (string) $response->headers->get('content-type', '(none)'));
        $body = (string) $response->getContent();
        $bodyPreview = trim(substr(strip_tags($body), 0, 800));
        line('Pipeline body preview', $bodyPreview === '' ? '(empty)' : $bodyPreview);
        $kernel->terminate($request, $response);
    } else {
        line('Pipeline status code', 'SKIP');
    }
} catch (Throwable $e) {
    line('Pipeline status code', 'FAIL');
    line('Pipeline error', $e->getMessage());
    line('Pipeline error class', get_class($e));
    $trace = $e->getTraceAsString();
    $firstTraceLine = strtok($trace, PHP_EOL);
    line('Pipeline trace top', $firstTraceLine !== false ? $firstTraceLine : '(none)');
}
echo PHP_EOL;

echo "---- Bootstrap Cache State ----" . PHP_EOL;
try {
    $cacheDir = $logicPath . '/bootstrap/cache';
    $cacheFiles = ['config.php', 'services.php', 'packages.php'];
    foreach ($cacheFiles as $cf) {
        $p = $cacheDir . '/' . $cf;
        line($cf . ' exists', ok(is_file($p)));
        if (is_file($p)) {
            line($cf . ' size', (string) filesize($p));
        }
    }

    if (isset($app) && is_object($app)) {
        $hasConfig = $app->bound('config');
        line('container has config', ok($hasConfig));
        if ($hasConfig) {
            $providers = $app['config']->get('app.providers', []);
            line('app.providers is array', ok(is_array($providers)));
            line('app.providers count', is_array($providers) ? (string) count($providers) : '0');
            if (is_array($providers)) {
                line('has ViewServiceProvider', ok(in_array(Illuminate\View\ViewServiceProvider::class, $providers, true)));
            }
        }
    }
} catch (Throwable $e) {
    line('Bootstrap cache probe', 'FAIL');
    line('Bootstrap cache error', $e->getMessage());
}
echo PHP_EOL;

echo "---- View/Session Paths ----" . PHP_EOL;
try {
    $pathChecks = [
        'storage/framework' => $logicPath . '/storage/framework',
        'storage/framework/views' => $logicPath . '/storage/framework/views',
        'storage/framework/sessions' => $logicPath . '/storage/framework/sessions',
        'storage/framework/cache' => $logicPath . '/storage/framework/cache',
        'storage/framework/cache/data' => $logicPath . '/storage/framework/cache/data',
    ];
    foreach ($pathChecks as $label => $p) {
        line($label . ' exists', ok(is_dir($p)));
        line($label . ' writable', ok(is_dir($p) && is_writable($p)));
    }
} catch (Throwable $e) {
    line('View/session path probe', 'FAIL');
    line('View/session path error', $e->getMessage());
}
echo PHP_EOL;

echo "---- Last Laravel Log Snippet ----" . PHP_EOL;
try {
    $logFile = $logicPath . '/storage/logs/laravel.log';
    if (!is_file($logFile)) {
        line('laravel.log', 'NOT FOUND');
    } else {
        line('laravel.log exists', 'OK');
        $size = filesize($logFile);
        line('laravel.log size', (string) $size);
        $readBytes = min(6000, max(0, (int) $size));
        $fp = fopen($logFile, 'rb');
        if ($fp === false) {
            line('laravel.log read', 'FAIL');
        } else {
            if ($readBytes > 0) {
                fseek($fp, -$readBytes, SEEK_END);
            }
            $chunk = stream_get_contents($fp);
            fclose($fp);
            if (!is_string($chunk) || trim($chunk) === '') {
                line('laravel.log tail', '(empty)');
            } else {
                echo "--- LOG TAIL START ---" . PHP_EOL;
                echo $chunk . PHP_EOL;
                echo "--- LOG TAIL END ---" . PHP_EOL;
            }
        }
    }
} catch (Throwable $e) {
    line('log probe', 'FAIL');
    line('log probe error', $e->getMessage());
}
echo PHP_EOL;

echo "---- Agent install instrumentation (session 6ca688) ----" . PHP_EOL;
line('Also open', '/test_install_debug.php (step replay + NDJSON log dump)');
echo PHP_EOL;

echo "==== End of Diagnostic ====" . PHP_EOL;
echo "Security note: delete this file after debugging." . PHP_EOL;

