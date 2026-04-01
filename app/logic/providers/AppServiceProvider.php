<?php

namespace App\Providers;

use App\Services\Cron\CronService;
use App\Services\Platform\Adapters\DiscordAdapter;
use App\Services\Platform\Adapters\FacebookAdapter;
use App\Services\Platform\Adapters\GoogleBusinessAdapter;
use App\Services\Platform\Adapters\InstagramAdapter;
use App\Services\Platform\Adapters\LinkedInAdapter;
use App\Services\Platform\Adapters\PinterestAdapter;
use App\Services\Platform\Adapters\RedditAdapter;
use App\Services\Platform\Adapters\TelegramAdapter;
use App\Services\Platform\Adapters\ThreadsAdapter;
use App\Services\Platform\Adapters\TikTokAdapter;
use App\Services\Platform\Adapters\TwitterAdapter;
use App\Services\Platform\Adapters\WordPressAdapter;
use App\Services\Platform\Adapters\YouTubeAdapter;
use App\Services\Platform\PlatformRegistry;
use App\Services\Post\PostPublishingService;
use App\Services\SocialAccount\TokenRefreshService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $installedMarker = dirname(base_path(), 2) . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . 'installed';
        if (! is_file($installedMarker)) {
            config([
                'cache.default' => 'file',
                'session.driver' => 'file',
            ]);
        }

        $this->app->singleton(PlatformRegistry::class, function () {
            $registry = new PlatformRegistry();

            $registry->register(new TwitterAdapter());
            $registry->register(new FacebookAdapter());
            $registry->register(new InstagramAdapter());
            $registry->register(new LinkedInAdapter());
            $registry->register(new TikTokAdapter());
            $registry->register(new YouTubeAdapter());
            $registry->register(new TelegramAdapter());
            $registry->register(new PinterestAdapter());
            $registry->register(new ThreadsAdapter());
            $registry->register(new RedditAdapter());
            $registry->register(new WordPressAdapter());
            $registry->register(new GoogleBusinessAdapter());
            $registry->register(new DiscordAdapter());

            return $registry;
        });

        $this->app->singleton(CronService::class, function ($app) {
            $cron = new CronService();

            $cron->register('publish_due_posts', function () use ($app) {
                $dispatched = $app->make(PostPublishingService::class)->publishDuePosts();
                return "Dispatched {$dispatched} post(s).";
            });

            $cron->register('refresh_tokens', function () use ($app) {
                $refreshed = $app->make(TokenRefreshService::class)->refreshExpiringSoon();
                return "Refreshed {$refreshed} token(s).";
            });

            $cron->register('purge_old_logs', function () {
                Artisan::call('logs:purge', ['--days' => 14]);
                return trim(Artisan::output());
            });

            return $cron;
        });
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            // #region agent log
            $vn = $view->name();
            $isInstall = str_starts_with($vn, 'install.');
            $logFile = dirname(base_path(), 2) . DIRECTORY_SEPARATOR . 'debug-6ca688.log';
            $payload = [
                'sessionId' => '6ca688',
                'timestamp' => (int) round(microtime(true) * 1000),
                'location' => 'AppServiceProvider.php:ViewComposer',
                'message' => 'composer',
                'data' => ['viewName' => $vn, 'isInstallPrefix' => $isInstall],
                'hypothesisId' => 'H4',
                'runId' => $GLOBALS['agent_log_run_id'] ?? 'pre-fix',
            ];
            @file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            $view->with('appName', config('app.name'));
            if ($isInstall) {
                $view->with('currentUser', null);
                return;
            }
            // #region agent log
            @file_put_contents($logFile, json_encode([
                'sessionId' => '6ca688',
                'timestamp' => (int) round(microtime(true) * 1000),
                'location' => 'AppServiceProvider.php:ViewComposer',
                'message' => 'before Auth::user',
                'data' => ['viewName' => $vn],
                'hypothesisId' => 'H1',
                'runId' => $GLOBALS['agent_log_run_id'] ?? 'pre-fix',
            ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            $view->with('currentUser', Auth::user());
        });
    }
}
