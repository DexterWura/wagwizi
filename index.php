<?php

/**
 * PostAI — Social Media Management Platform
 *
 * All requests funnel through this file. Apache rewrites
 * (see .htaccess) send everything here except static assets.
 */

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
define('PROJECT_ROOT', __DIR__);
define('LOGIC_PATH', __DIR__ . '/app/logic');

require LOGIC_PATH . '/vendor/autoload.php';

$app = require_once LOGIC_PATH . '/bootstrap.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
