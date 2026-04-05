<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace'   => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['weekly', 'single'],
            'ignore_exceptions' => false,
        ],

        /*
        |----------------------------------------------------------------------
        | Weekly rotation (default)
        |----------------------------------------------------------------------
        | Writes to storage/logs/laravel-YYYY-WNN.log (ISO week number).
        | A new file starts automatically every Monday.
        | The PurgeOldLogs command deletes files older than 14 days.
        */
        'weekly' => [
            'driver' => 'single',
            'path'   => storage_path('logs/laravel-' . date('o-\WW') . '.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => 14,
            'replace_placeholders' => true,
        ],

        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver'     => 'monolog',
            'level'      => env('LOG_LEVEL', 'debug'),
            'handler'    => StreamHandler::class,
            'with'       => ['stream' => 'php://stderr'],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver'   => 'syslog',
            'level'    => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level'  => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
