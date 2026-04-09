<?php

return [

    'name' => env('APP_NAME', 'PostAI'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    /*
    | When true (default), HTTP requests are redirected to HTTPS and generated URLs use https,
    | except in local/testing. Set FORCE_HTTPS=false if you must run without TLS (not recommended).
    */
    'force_https' => filter_var(env('FORCE_HTTPS', true), FILTER_VALIDATE_BOOLEAN),

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => 'en',

    'faker_locale' => 'en_US',

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    // Used only when no cron token is stored in site_settings (see CronSecretResolver).
    'cron_secret' => env('CRON_SECRET'),

    /*
    | When true (default), posts picked up by publishDuePosts() run PublishPostToPlatformJob
    | synchronously so scheduled publishing works without a separate queue worker.
    */
    'publish_due_posts_sync' => filter_var(env('PUBLISH_DUE_POSTS_SYNC', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | When true, all publish jobs (immediate + scheduled) and eligible first-comment jobs run
    | synchronously — use when you do not run queue workers at all.
    */
    'publish_all_jobs_sync' => filter_var(env('PUBLISH_ALL_JOBS_SYNC', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | When true (without publish_all_jobs_sync), first comments publish synchronously if delay is 0.
    */
    'publish_comment_jobs_sync' => filter_var(env('PUBLISH_COMMENT_JOBS_SYNC', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Serialize publishes targeting the same social account to reduce rate-limit races.
    */
    'publish_per_account_lock' => filter_var(env('PUBLISH_PER_ACCOUNT_LOCK', true), FILTER_VALIDATE_BOOLEAN),

    'maintenance' => [
        'driver' => 'file',
    ],

    'providers' => [
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,
        App\Providers\AppServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ],

    'aliases' => Illuminate\Support\Facades\Facade::defaultAliases()->merge([
    ])->toArray(),

];
