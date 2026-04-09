<?php

namespace App\Providers;

use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\Timezone;
use App\Services\Cron\CronService;
use App\Services\Platform\Adapters\BlueskyAdapter;
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
use App\Services\Platform\Adapters\WhatsAppChannelsAdapter;
use App\Services\Platform\Adapters\YouTubeAdapter;
use App\Services\Platform\PlatformRegistry;
use App\Services\Post\PostPublishingService;
use App\Services\SocialAccount\TokenRefreshService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use App\Services\Ai\PlatformAiQuotaService;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Support\Facades\URL;
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
            $registry->register(new BlueskyAdapter());
            $registry->register(new WhatsAppChannelsAdapter());

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

            $cron->register('refresh_tokens_unknown_expiry', function () use ($app) {
                $refreshed = $app->make(TokenRefreshService::class)->refreshAccountsWithUnknownExpiry();
                return "Refreshed {$refreshed} account(s) with unknown token expiry.";
            });

            $cron->register('purge_old_logs', function () {
                Artisan::call('logs:purge', ['--days' => 14]);
                return trim(Artisan::output());
            });

            $cron->register('inapp_expiry_reminders', function () use ($app) {
                $app->make(InAppNotificationService::class)->sendScheduledExpiryReminders();

                return 'In-app expiry reminders processed.';
            });

            $cron->register('pending_migrations_alert', function () use ($app) {
                $app->make(InAppNotificationService::class)->notifySuperAdminsIfPendingMigrations();

                return 'Pending migrations in-app check done.';
            });

            return $cron;
        });
    }

    public function boot(): void
    {
        $this->ensureLogFilesExist();

        Event::listen(JobFailed::class, function (JobFailed $event): void {
            try {
                $name = (string) ($event->job->payload()['displayName'] ?? '');
                if ($name === '' || ! str_starts_with($name, 'App\\Jobs\\')) {
                    return;
                }

                $handledInDetail = [
                    'App\\Jobs\\SendTemplatedEmailJob',
                    'App\\Jobs\\PublishPostToPlatformJob',
                    'App\\Jobs\\PublishPostCommentJob',
                    'App\\Jobs\\SendMarketingCampaignBatchJob',
                    'App\\Jobs\\RefreshExpiredTokensJob',
                ];
                if (in_array($name, $handledInDetail, true)) {
                    return;
                }

                app(InAppNotificationService::class)->notifySuperAdminsOperationalAlert(
                    'admin_critical_queue_job',
                    'Background job failed',
                    $name . ': ' . mb_substr($event->exception->getMessage(), 0, 500),
                    route('admin.operations'),
                    ['job' => $name],
                    'queue_fail:' . $name . ':' . md5($event->exception->getMessage()),
                    3600,
                );
            } catch (\Throwable) {
            }
        });

        if (filter_var(config('app.force_https', true), FILTER_VALIDATE_BOOLEAN)
            && ! $this->app->environment('local', 'testing')) {
            URL::forceScheme('https');
        }

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

        $installedMarker = dirname(base_path(), 2) . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . 'installed';
        $this->app->booted(function () use ($installedMarker): void {
            if ($this->app->runningInConsole() || ! is_file($installedMarker)) {
                return;
            }

            try {
                Cache::remember('global:pending_migrations_inapp_check', 3600, function (): true {
                    app(InAppNotificationService::class)->notifySuperAdminsIfPendingMigrations();

                    return true;
                });
            } catch (\Throwable) {
            }
        });

        View::composer('*', function ($view) {
            $view->with('appName', config('app.name'));

            $timezoneData = $this->cachedTimezoneData();
            $view->with('displayTimezonesList', $timezoneData['list']);
            $view->with('displayTimezonesMeta', $timezoneData['meta']);
            $view->with('defaultDisplayTimezoneIdentifier', $timezoneData['default']);

            $view->with('showFloatingHelp', $this->cachedShowFloatingHelp());
            $view->with('seoDefaults', $this->cachedSeoDefaults());
            $view->with('aiClientConfig', null);

            if (str_starts_with((string) $view->name(), 'install.')) {
                $view->with('currentUser', null);
                $view->with('showTrialEndedBanner', false);
                $view->with('freePlanSlug', null);
                $view->with('showSubscriptionRenewalBanner', false);
                $view->with('subscriptionRenewalDaysLeft', null);
                $view->with('unreadNotificationCount', 0);

                return;
            }

            $u = Auth::user();
            $view->with('currentUser', $u);

            if ($u === null) {
                $view->with('showTrialEndedBanner', false);
                $view->with('freePlanSlug', null);
                $view->with('showSubscriptionRenewalBanner', false);
                $view->with('subscriptionRenewalDaysLeft', null);
                $view->with('unreadNotificationCount', 0);

                return;
            }

            $view->with('freePlanSlug', $this->cachedFreePlanSlug());

            $showTrialEndedBanner = false;
            try {
                $showTrialEndedBanner = $u->isSubscriptionPastDueAfterTrial();
            } catch (\Throwable) {
            }
            $view->with('showTrialEndedBanner', $showTrialEndedBanner);

            $subscriptionRenewalDaysLeft   = null;
            $showSubscriptionRenewalBanner = false;
            try {
                $u->loadMissing('subscription.planModel');
                $sub = $u->subscription;
                if ($sub && $sub->status === 'active' && $sub->current_period_end && $sub->current_period_end->isFuture()) {
                    $pl = $sub->planModel;
                    if ($pl && ! $pl->is_free && ! $pl->is_lifetime) {
                        $subscriptionRenewalDaysLeft = (int) now()->diffInDays($sub->current_period_end, true);
                        $showSubscriptionRenewalBanner = $subscriptionRenewalDaysLeft <= 7;
                    }
                }
            } catch (\Throwable) {
            }
            $view->with('subscriptionRenewalDaysLeft', $subscriptionRenewalDaysLeft);
            $view->with('showSubscriptionRenewalBanner', $showSubscriptionRenewalBanner);

            try {
                try {
                    $tokenSummary = app(PlatformAiQuotaService::class)->summaryForLayout($u);
                } catch (\Throwable) {
                    $tokenSummary = ['remaining' => 0, 'budget' => 0, 'applies' => false];
                }
                $aiClientConfig = [
                    'source'    => $u->ai_source === 'byok' ? 'byok' : 'platform',
                    'provider'  => in_array($u->ai_provider, ['openai', 'anthropic', 'custom'], true)
                        ? $u->ai_provider
                        : 'openai',
                    'baseUrl'   => (string) ($u->ai_base_url ?? ''),
                    'hasApiKey' => $u->hasAiApiKeyStored(),
                    'platformTokensRemaining' => $tokenSummary['remaining'],
                    'platformTokensBudget'    => $tokenSummary['budget'],
                    'platformTokensApplies'   => $tokenSummary['applies'],
                ];
            } catch (\Throwable) {
                $aiClientConfig = [
                    'source'    => 'platform',
                    'provider'  => 'openai',
                    'baseUrl'   => '',
                    'hasApiKey' => false,
                ];
            }
            $view->with('aiClientConfig', $aiClientConfig);

            $unreadNotificationCount = Cache::remember(
                "unread_notif_count:{$u->id}",
                60,
                function () use ($u) {
                    try {
                        return Schema::hasTable('notifications')
                            ? $u->unreadNotifications()->count()
                            : 0;
                    } catch (\Throwable) {
                        return 0;
                    }
                },
            );
            $view->with('unreadNotificationCount', $unreadNotificationCount);
        });
    }

    private function cachedTimezoneData(): array
    {
        return Cache::remember('global:timezone_data', 3600, function () {
            $list    = collect();
            $meta    = [];
            $default = 'UTC';

            try {
                if (Schema::hasTable('timezones')) {
                    $rows = Timezone::query()->orderBy('identifier')->get();
                    if ($rows->isNotEmpty()) {
                        $list = $rows;
                        $meta = $rows->mapWithKeys(static fn (Timezone $tz) => [
                            $tz->identifier => [
                                'code'   => $tz->label_short,
                                'name'   => $tz->label_long,
                                'symbol' => '',
                            ],
                        ])->all();
                        $default = SiteSetting::get('default_display_timezone', 'UTC');
                        if (! $rows->contains('identifier', $default)) {
                            $default = $rows->first()->identifier;
                        }
                    }
                }
            } catch (\Throwable) {
            }

            return ['list' => $list, 'meta' => $meta, 'default' => $default];
        });
    }

    private function cachedShowFloatingHelp(): bool
    {
        return Cache::remember('global:show_floating_help', 3600, function () {
            try {
                if (Schema::hasTable('site_settings')) {
                    $v = SiteSetting::get('show_floating_help', '1');
                    return $v === '1' || $v === 1 || $v === true;
                }
            } catch (\Throwable) {
            }

            return true;
        });
    }

    private function cachedSeoDefaults(): array
    {
        return Cache::remember('global:seo_defaults', 3600, function () {
            $siteName = (string) config('app.name');
            $tagline = '';
            $metaTitle = '';
            $metaDescription = '';
            $socialDescription = '';
            $keywords = '';
            $twitterSite = '';
            $imagePath = '';
            $faviconPath = '';

            try {
                if (Schema::hasTable('site_settings')) {
                    $siteName = trim((string) SiteSetting::get('app_name', $siteName)) ?: $siteName;
                    $tagline = trim((string) SiteSetting::get('app_tagline', ''));
                    $metaTitle = trim((string) SiteSetting::get('seo_meta_title', ''));
                    $metaDescription = trim((string) SiteSetting::get('seo_meta_description', ''));
                    $socialDescription = trim((string) SiteSetting::get('seo_social_description', ''));
                    $keywords = trim((string) SiteSetting::get('seo_keywords', ''));
                    $twitterSite = trim((string) SiteSetting::get('seo_twitter_site', ''));
                    $imagePath = trim((string) SiteSetting::get('seo_image_path', ''));
                    $faviconPath = trim((string) SiteSetting::get('seo_favicon_path', ''));
                }
            } catch (\Throwable) {
            }

            if ($metaTitle === '') {
                $metaTitle = $siteName;
            }
            if ($metaDescription === '') {
                $metaDescription = $tagline !== ''
                    ? $tagline
                    : 'Plan, schedule, and publish social content across multiple platforms from one dashboard.';
            }
            if ($socialDescription === '') {
                $socialDescription = $metaDescription;
            }
            if ($keywords === '') {
                $keywords = 'social media scheduler, social media manager, content calendar, post scheduling, social analytics';
            }

            $imageUrl = '';
            if ($imagePath !== '') {
                if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                    $imageUrl = $imagePath;
                } else {
                    $imageUrl = url('/' . ltrim($imagePath, '/'));
                }
            }

            $faviconUrl = '';
            if ($faviconPath !== '') {
                if (filter_var($faviconPath, FILTER_VALIDATE_URL)) {
                    $faviconUrl = $faviconPath;
                } else {
                    $faviconUrl = url('/' . ltrim($faviconPath, '/'));
                }
            }

            return [
                'site_name'          => $siteName,
                'tagline'            => $tagline,
                'meta_title'         => $metaTitle,
                'meta_description'   => $metaDescription,
                'social_description' => $socialDescription,
                'keywords'           => $keywords,
                'twitter_site'       => $twitterSite,
                'image_url'          => $imageUrl,
                'favicon_url'        => $faviconUrl,
                'robots'             => 'index,follow',
                'type'               => 'website',
            ];
        });
    }

    private function cachedFreePlanSlug(): ?string
    {
        return Cache::remember('global:free_plan_slug', 3600, function () {
            try {
                if (Schema::hasTable('plans')) {
                    $fp = Plan::query()->where('is_active', true)->where('is_free', true)->orderBy('sort_order')->first();
                    return $fp?->slug;
                }
            } catch (\Throwable) {
            }

            return null;
        });
    }

    /**
     * Keep file logs available even if they were manually deleted.
     */
    private function ensureLogFilesExist(): void
    {
        try {
            $logDir = storage_path('logs');
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }

            if (!is_dir($logDir)) {
                return;
            }

            $single = $logDir . DIRECTORY_SEPARATOR . 'laravel.log';
            if (!file_exists($single)) {
                @touch($single);
            }

            $weekly = $logDir . DIRECTORY_SEPARATOR . 'laravel-' . date('o-\WW') . '.log';
            if (!file_exists($weekly)) {
                @touch($weekly);
            }
        } catch (\Throwable) {
            // Never block app boot if filesystem permissions are restricted.
        }
    }
}
