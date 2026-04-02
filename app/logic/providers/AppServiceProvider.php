<?php

namespace App\Providers;

use App\Models\SiteSetting;
use App\Models\Timezone;
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
use Illuminate\Support\Facades\Schema;
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
        $this->app->booted(function (): void {
            try {
                if (Schema::hasTable('site_settings')) {
                    $name = SiteSetting::get('app_name', config('app.name'));
                    if (is_string($name) && $name !== '') {
                        config(['app.name' => $name]);
                    }
                }
            } catch (\Throwable) {
                // Installer, missing DB, or migrations not run yet.
            }
        });

        View::composer('*', function ($view) {
            $view->with('appName', config('app.name'));

            $displayTimezonesList = collect();
            $displayTimezonesMeta = [];
            $defaultDisplayTimezoneIdentifier = 'UTC';

            try {
                if (Schema::hasTable('timezones')) {
                    $list = Timezone::query()->orderBy('identifier')->get();
                    if ($list->isNotEmpty()) {
                        $displayTimezonesList = $list;
                        $displayTimezonesMeta = $list->mapWithKeys(static function (Timezone $tz): array {
                            return [
                                $tz->identifier => [
                                    'code'   => $tz->label_short,
                                    'name'   => $tz->label_long,
                                    'symbol' => '',
                                ],
                            ];
                        })->all();
                        $defaultDisplayTimezoneIdentifier = SiteSetting::get('default_display_timezone', 'UTC');
                        if (! $list->contains('identifier', $defaultDisplayTimezoneIdentifier)) {
                            $defaultDisplayTimezoneIdentifier = $list->first()->identifier;
                        }
                    }
                }
            } catch (\Throwable) {
                // Installer, missing DB, or migrations not run yet.
            }

            $view->with('displayTimezonesList', $displayTimezonesList);
            $view->with('displayTimezonesMeta', $displayTimezonesMeta);
            $view->with('defaultDisplayTimezoneIdentifier', $defaultDisplayTimezoneIdentifier);

            if (str_starts_with($view->name(), 'install.')) {
                $view->with('currentUser', null);
                return;
            }
            $view->with('currentUser', Auth::user());
        });
    }
}
