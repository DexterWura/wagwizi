<?php

$projectRoot = dirname(__DIR__, 2);

return [

    'paths' => [
        $projectRoot . '/app/frontend/pages',
        $projectRoot . '/app/frontend/layouts',
        $projectRoot . '/app/frontend/partials',
        $projectRoot . '/app/frontend/sections',
    ],

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        dirname(__DIR__) . '/storage/framework/views'
    ),

];
