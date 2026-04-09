<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;

define('PROJECT_ROOT', __DIR__);
define('LOGIC_PATH', __DIR__ . '/app/logic');

require LOGIC_PATH . '/vendor/autoload.php';

$app = require_once LOGIC_PATH . '/bootstrap.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Facade::setFacadeApplication($app);

/** @var string $provided */
$provided = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$expected = '8M3gi8xCdAUJfduvvTsCdKUP05WxIG5Z1f4h2Z55nEzt5Kul';

header('Content-Type: application/json; charset=utf-8');

if ($provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Forbidden.',
    ]);
    exit;
}

try {
    $commands = ['optimize:clear', 'config:clear', 'route:clear', 'view:clear', 'cache:clear'];
    $results = [];

    foreach ($commands as $command) {
        Artisan::call($command);
        $results[] = [
            'command' => $command,
            'output' => trim(Artisan::output()),
        ];
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Caches cleared.',
        'results' => $results,
        'at' => gmdate('c'),
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Failed to clear caches.',
        'error' => $e->getMessage(),
    ]);
}

