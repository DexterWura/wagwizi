<?php

use App\Controllers\AdminController;
use App\Controllers\ComposerAiController;
use App\Controllers\CronController;
use App\Controllers\AdminMarketingController;
use App\Controllers\AdminNotificationController;
use App\Controllers\Auth\AuthController;
use App\Controllers\Auth\SocialAuthController;
use App\Controllers\MediaController;
use App\Controllers\NotificationController;
use App\Controllers\PageController;
use App\Controllers\ProfileController;
use App\Controllers\SettingsController;
use App\Controllers\SocialAccountController;
use App\Controllers\StatusController;
use App\Controllers\SupportTicketController;
use App\Controllers\PaynowWebhookController;
use App\Controllers\PesepayWebhookController;
use App\Controllers\PlanCheckoutController;
use App\Controllers\PostController;
use App\Controllers\StripeWebhookController;
use App\Controllers\PaypalWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::get('/', [PageController::class, 'landing'])->name('landing');

Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');

Route::get('/status', [StatusController::class, 'show'])->name('status');

/*
| cPanel-friendly cron trigger: GET or POST /cron?token=YOUR_CRON_SECRET
| (Same handler as POST /api/cron/run with X-Cron-Secret.)
*/
Route::match(['get', 'post'], '/cron', [CronController::class, 'run'])
    ->middleware('throttle:10,1')
    ->name('cron.run');

/*
|--------------------------------------------------------------------------
| Guest-only auth routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password', [AuthController::class, 'showResetPassword'])
        ->middleware('signed')
        ->name('password.reset.form');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1')
        ->name('password.update');

    Route::get('/signup',  [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup'])->middleware('throttle:5,1');

    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::post('/paynow/result', [PaynowWebhookController::class, 'result'])->name('paynow.result');
Route::match(['get', 'post'], '/pesepay/result', [PesepayWebhookController::class, 'result'])->name('pesepay.result');
Route::post('/stripe/webhook', [StripeWebhookController::class, 'result'])->name('stripe.webhook');
Route::post('/paypal/webhook', [PaypalWebhookController::class, 'result'])->name('paypal.webhook');

/*
|--------------------------------------------------------------------------
| Authenticated app routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/dashboard',     [PageController::class, 'dashboard'])->name('dashboard');
    Route::get('/composer',      [PageController::class, 'composer'])->name('composer');
    Route::get('/posts',         [PageController::class, 'posts'])->name('posts.index');
    Route::get('/posts/data',    [PostController::class, 'index'])->name('posts.data');
    Route::get('/calendar',      [PageController::class, 'calendar'])->name('calendar');
    Route::get('/media-library', [PageController::class, 'mediaLibrary'])->name('media-library');
    Route::get('/accounts',      [PageController::class, 'accounts'])->name('accounts');
    Route::get('/insights',      [PageController::class, 'insights'])->name('insights');
    Route::get('/plans',         [PageController::class, 'plans'])->name('plans');
    Route::get('/plan-history',  [PageController::class, 'planHistory'])->name('plan-history');
    Route::get('/profile',       [PageController::class, 'profile'])->name('profile');
    Route::get('/settings',      [PageController::class, 'settings'])->name('settings');

    Route::get('/accounts/{platform}/connect',   [SocialAccountController::class, 'connect'])->name('accounts.connect');
    Route::get('/accounts/{platform}/callback',  [SocialAccountController::class, 'callback'])->name('accounts.callback');
    Route::post('/accounts/telegram',            [SocialAccountController::class, 'storeTelegram'])->name('accounts.telegram');
    Route::post('/accounts/wordpress',           [SocialAccountController::class, 'storeWordPress'])->name('accounts.wordpress');
    Route::post('/accounts/discord',             [SocialAccountController::class, 'storeDiscord'])->name('accounts.discord');
    Route::post('/accounts/bluesky',             [SocialAccountController::class, 'storeBluesky'])->name('accounts.bluesky');
    Route::post('/accounts/whatsapp-channels',   [SocialAccountController::class, 'storeWhatsappChannels'])->name('accounts.whatsapp-channels');
    Route::post('/accounts/{accountId}/disconnect', [SocialAccountController::class, 'disconnect'])->name('accounts.disconnect');

    Route::post('/profile',          [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar',   [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::post('/settings/workspace',     [SettingsController::class, 'updateWorkspace'])->name('settings.workspace');
    Route::post('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    Route::post('/settings/default-time',  [SettingsController::class, 'updateDefaultTime'])->name('settings.default-time');
    Route::post('/settings/ai',            [SettingsController::class, 'updateAiSettings'])
        ->middleware('throttle:20,1')
        ->name('settings.ai');
    Route::post('/composer/ai',            [ComposerAiController::class, 'chat'])
        ->middleware(['throttle:30,1', 'plan_tool:ai_caption_generator'])
        ->name('composer.ai');

    Route::get('/support-tickets',        [SupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::get('/support-tickets/{id}',   [SupportTicketController::class, 'show'])->name('support-tickets.show')->whereNumber('id');
    Route::post('/support-tickets/{id}/reply', [SupportTicketController::class, 'reply'])->name('support-tickets.reply')->whereNumber('id');
    Route::post('/support-tickets',       [SupportTicketController::class, 'store'])->name('support-tickets.store');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read-one');

    Route::get('/media',  [MediaController::class, 'index'])->name('media.index');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');

    Route::post('/plans/change', [PageController::class, 'changePlan'])->name('plans.change');
    Route::post('/plans/checkout/start', [PlanCheckoutController::class, 'startCheckout'])->name('plans.checkout.start');
    Route::post('/plans/paynow/start', [PlanCheckoutController::class, 'startPaynow'])->name('plans.paynow.start');
    Route::get('/plans/paynow/return', [PlanCheckoutController::class, 'paynowReturn'])->name('plans.paynow.return');
    Route::post('/plans/pesepay/start', [PlanCheckoutController::class, 'startPesepay'])->name('plans.pesepay.start');
    Route::get('/plans/pesepay/return', [PlanCheckoutController::class, 'pesepayReturn'])->name('plans.pesepay.return');
    Route::post('/plans/stripe/start', [PlanCheckoutController::class, 'startStripe'])->name('plans.stripe.start');
    Route::get('/plans/stripe/return', [PlanCheckoutController::class, 'stripeReturn'])->name('plans.stripe.return');
    Route::post('/plans/paypal/start', [PlanCheckoutController::class, 'startPaypal'])->name('plans.paypal.start');
    Route::get('/plans/paypal/return', [PlanCheckoutController::class, 'paypalReturn'])->name('plans.paypal.return');
    Route::post('/impersonation/leave', [AdminController::class, 'stopLoginAsUser'])->name('impersonation.leave');

    /*
    |----------------------------------------------------------------------
    | Super Admin routes
    |----------------------------------------------------------------------
    */

    Route::middleware('super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users',                [AdminController::class, 'users'])->name('users');
        Route::post('/users/{id}/role',     [AdminController::class, 'updateUserRole'])->name('users.role');
        Route::post('/users/{id}/status',   [AdminController::class, 'updateUserStatus'])->name('users.status');
        Route::post('/users/{id}/login-as', [AdminController::class, 'loginAsUser'])->name('users.login-as');
        Route::post('/users/{id}/plan',     [AdminController::class, 'updateUserPlan'])->name('users.plan');

        Route::get('/plans',                [AdminController::class, 'plans'])->name('plans');
        Route::post('/plans',               [AdminController::class, 'storePlan'])->name('plans.store');
        Route::post('/plans/tools',         [AdminController::class, 'updatePlanTools'])->name('plans.tools.update');
        Route::put('/plans/{id}',           [AdminController::class, 'updatePlan'])->name('plans.update');
        Route::delete('/plans/{id}',        [AdminController::class, 'destroyPlan'])->name('plans.destroy');

        Route::get('/platforms',            [AdminController::class, 'platforms'])->name('platforms');
        Route::post('/platforms',           [AdminController::class, 'updatePlatforms'])->name('platforms.update');

        Route::get('/testimonials',         [AdminController::class, 'testimonials'])->name('testimonials');
        Route::post('/testimonials',        [AdminController::class, 'storeTestimonial'])->name('testimonials.store');
        Route::put('/testimonials/{id}',    [AdminController::class, 'updateTestimonial'])->name('testimonials.update');
        Route::delete('/testimonials/{id}', [AdminController::class, 'destroyTestimonial'])->name('testimonials.destroy');

        Route::get('/faqs',         [AdminController::class, 'faqs'])->name('faqs');
        Route::post('/faqs',        [AdminController::class, 'storeFaq'])->name('faqs.store');
        Route::put('/faqs/{id}',    [AdminController::class, 'updateFaq'])->name('faqs.update');
        Route::delete('/faqs/{id}', [AdminController::class, 'destroyFaq'])->name('faqs.destroy');

        Route::get('/tickets',              [AdminController::class, 'tickets'])->name('tickets');
        Route::post('/tickets/{id}/reply',  [AdminController::class, 'replyTicket'])->name('tickets.reply');
        Route::post('/tickets/{id}/status', [AdminController::class, 'updateTicketStatus'])->name('tickets.status');

        Route::get('/settings',             [AdminController::class, 'settings'])->name('settings');
        Route::post('/settings',            [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::post('/settings/clear-cache', [AdminController::class, 'clearSiteCache'])->name('settings.clear-cache');
        Route::post('/settings/generate-sitemap', [AdminController::class, 'generateSitemap'])->name('settings.generate-sitemap');
        Route::post('/settings/generate-robots', [AdminController::class, 'generateRobotsTxt'])->name('settings.generate-robots');
        Route::post('/settings/landing-features-deep', [AdminController::class, 'updateLandingFeaturesDeep'])->name('settings.landing-features-deep');

        Route::get('/migrations',           [AdminController::class, 'migrations'])->name('migrations');
        Route::post('/migrations/run',      [AdminController::class, 'runMigrations'])->name('migrations.run');
        Route::post('/migrations/rollback', [AdminController::class, 'rollbackMigrations'])->name('migrations.rollback');

        Route::get('/payment-gateways', [AdminController::class, 'paymentGateways'])->name('payment-gateways');
        Route::post('/payment-gateways', [AdminController::class, 'updatePaymentGateways'])->name('payment-gateways.update');

        Route::get('/subscriptions', [AdminController::class, 'subscriptionsDashboard'])->name('subscriptions');
        Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
        Route::get('/payment-transactions', [AdminController::class, 'paymentTransactions'])->name('payment-transactions');

        Route::get('/operations',                [AdminController::class, 'operations'])->name('operations');
        Route::post('/operations/clear-cache',   [AdminController::class, 'clearApplicationCache'])->name('operations.clear-cache');
        Route::post('/operations/retry-publish', [AdminController::class, 'retryPublish'])->name('operations.retry-publish');
        Route::post('/operations/retry-comment', [AdminController::class, 'retryComment'])->name('operations.retry-comment');
        Route::post('/operations/settings',      [AdminController::class, 'updateOperationsSettings'])->name('operations.settings');

        Route::get('/cron-jobs',           [AdminController::class, 'cronJobs'])->name('cron-jobs');
        Route::post('/cron-jobs/run-due',  [AdminController::class, 'runDueCronTasksNow'])->name('cron-jobs.run-due');
        Route::post('/cron-jobs/{id}/run', [AdminController::class, 'runCronTaskNow'])->name('cron-jobs.run');
        Route::post('/cron-jobs/{id}',     [AdminController::class, 'updateCronJob'])->name('cron-jobs.update');

        Route::get('/notifications/settings', [AdminNotificationController::class, 'notificationSettings'])->name('notifications.settings');
        Route::post('/notifications/settings', [AdminNotificationController::class, 'updateNotificationSettings'])->name('notifications.settings.update');
        Route::post('/notifications/test-email', [AdminNotificationController::class, 'sendTestEmail'])->name('notifications.test-email');

        Route::get('/email-templates', [AdminNotificationController::class, 'emailTemplates'])->name('email-templates.index');
        Route::get('/email-templates/{id}/edit', [AdminNotificationController::class, 'editEmailTemplate'])->name('email-templates.edit');
        Route::put('/email-templates/{id}', [AdminNotificationController::class, 'updateEmailTemplate'])->name('email-templates.update');
        Route::get('/email-templates/{id}/preview', [AdminNotificationController::class, 'previewEmailTemplate'])->name('email-templates.preview');

        Route::get('/notification-deliveries', [AdminNotificationController::class, 'notificationDeliveries'])->name('notification-deliveries');

        Route::get('/marketing-campaigns', [AdminMarketingController::class, 'index'])->name('marketing-campaigns.index');
        Route::get('/marketing-campaigns/create', [AdminMarketingController::class, 'create'])->name('marketing-campaigns.create');
        Route::post('/marketing-campaigns', [AdminMarketingController::class, 'store'])->name('marketing-campaigns.store');
        Route::get('/marketing-campaigns/{id}/edit', [AdminMarketingController::class, 'edit'])->name('marketing-campaigns.edit');
        Route::put('/marketing-campaigns/{id}', [AdminMarketingController::class, 'update'])->name('marketing-campaigns.update');
        Route::delete('/marketing-campaigns/{id}', [AdminMarketingController::class, 'destroy'])->name('marketing-campaigns.destroy');
        Route::post('/marketing-campaigns/{id}/test', [AdminMarketingController::class, 'sendTest'])->name('marketing-campaigns.test');
        Route::post('/marketing-campaigns/{id}/send', [AdminMarketingController::class, 'startSend'])->name('marketing-campaigns.send');
    });
});
