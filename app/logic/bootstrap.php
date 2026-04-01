<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$projectRoot = dirname(__DIR__, 2);
$logicPath   = __DIR__;

// Shared-hosting fallback autoloader for App\ classes.
// This keeps the app bootable when vendor autoload metadata is stale.
spl_autoload_register(function (string $class) use ($logicPath): void {
    $prefixMap = [
        'App\\Controllers\\' => $logicPath . '/controllers/',
        'App\\Models\\'      => $logicPath . '/models/',
        'App\\Services\\'    => $logicPath . '/services/',
        'App\\Jobs\\'        => $logicPath . '/jobs/',
        'App\\Providers\\'   => $logicPath . '/providers/',
        'App\\Http\\'        => $logicPath . '/Http/',
        'App\\Console\\'     => $logicPath . '/Console/',
        'App\\Utils\\'       => $logicPath . '/utils/',
    ];

    foreach ($prefixMap as $prefix => $basePath) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = $basePath . $relative . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }
});

if (!is_dir($logicPath . '/bootstrap/cache')) {
    @mkdir($logicPath . '/bootstrap/cache', 0775, true);
}

$storagePath = $logicPath . DIRECTORY_SEPARATOR . 'storage';
foreach (
    [
        $storagePath . '/framework/sessions',
        $storagePath . '/framework/views',
        $storagePath . '/framework/cache/data',
        $storagePath . '/logs',
    ] as $dir
) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

$app = new Application($logicPath);

// Laravel discovers App\ via composer PSR-4 where realpath(app/) === realpath(path()).
// If app/ is missing on deploy or realpath() fails on the host, Console\Kernel::bootstrap() throws.
$applicationNamespace = (new \ReflectionClass($app))->getProperty('namespace');
$applicationNamespace->setAccessible(true);
$applicationNamespace->setValue($app, 'App\\');

$app->useEnvironmentPath($projectRoot . DIRECTORY_SEPARATOR . 'secrets');
$app->useStoragePath($storagePath);
$app->useDatabasePath($logicPath . DIRECTORY_SEPARATOR . 'database');

$app->bind('path.public', fn () => $projectRoot);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    Illuminate\Foundation\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Fresh install: drop stale provider manifest
|--------------------------------------------------------------------------
|
| Zipped deployments often include bootstrap/cache/services.php from another
| machine. That manifest can omit eager providers (e.g. View), causing 500s.
| Only clear it before installation is marked complete.
|
*/
$installedMarker = $projectRoot . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . 'installed';
$bootstrapCache  = $logicPath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
if (! is_file($installedMarker)) {
    foreach (['services.php', 'packages.php', 'config.php'] as $cacheFile) {
        $p = $bootstrapCache . DIRECTORY_SEPARATOR . $cacheFile;
        if (is_file($p)) {
            @unlink($p);
        }
    }
}

return $app;
