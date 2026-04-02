<?php

use App\Controllers\AdminController;
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
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::get('/', [PageController::class, 'landing'])->name('landing');

Route::get('/status', [StatusController::class, 'show'])->name('status');

/*
|--------------------------------------------------------------------------
| Guest-only auth routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/signup',  [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup'])->middleware('throttle:5,1');

    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Authenticated app routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/dashboard',     [PageController::class, 'dashboard'])->name('dashboard');
    Route::get('/composer',      [PageController::class, 'composer'])->name('composer');
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
    Route::post('/accounts/{accountId}/disconnect', [SocialAccountController::class, 'disconnect'])->name('accounts.disconnect');

    Route::post('/profile',          [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar',   [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::post('/settings/workspace',     [SettingsController::class, 'updateWorkspace'])->name('settings.workspace');
    Route::post('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    Route::post('/settings/default-time',  [SettingsController::class, 'updateDefaultTime'])->name('settings.default-time');
    Route::post('/settings/ai',            [SettingsController::class, 'updateAiSettings'])->name('settings.ai');

    Route::get('/support-tickets',        [SupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::get('/support-tickets/{id}',   [SupportTicketController::class, 'show'])->name('support-tickets.show')->whereNumber('id');
    Route::post('/support-tickets/{id}/reply', [SupportTicketController::class, 'reply'])->name('support-tickets.reply')->whereNumber('id');
    Route::post('/support-tickets',       [SupportTicketController::class, 'store'])->name('support-tickets.store');

    Route::get('/notifications',      [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');

    Route::get('/media',  [MediaController::class, 'index'])->name('media.index');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');

    Route::post('/plans/change', [PageController::class, 'changePlan'])->name('plans.change');

    /*
    |----------------------------------------------------------------------
    | Super Admin routes
    |----------------------------------------------------------------------
    */

    Route::middleware('super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users',                [AdminController::class, 'users'])->name('users');
        Route::post('/users/{id}/role',     [AdminController::class, 'updateUserRole'])->name('users.role');
        Route::post('/users/{id}/status',   [AdminController::class, 'updateUserStatus'])->name('users.status');

        Route::get('/plans',                [AdminController::class, 'plans'])->name('plans');
        Route::post('/plans',               [AdminController::class, 'storePlan'])->name('plans.store');
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

        Route::get('/migrations',           [AdminController::class, 'migrations'])->name('migrations');
        Route::post('/migrations/run',      [AdminController::class, 'runMigrations'])->name('migrations.run');
        Route::post('/migrations/rollback', [AdminController::class, 'rollbackMigrations'])->name('migrations.rollback');

        Route::get('/operations',                [AdminController::class, 'operations'])->name('operations');
        Route::post('/operations/clear-cache',   [AdminController::class, 'clearApplicationCache'])->name('operations.clear-cache');
        Route::post('/operations/retry-publish', [AdminController::class, 'retryPublish'])->name('operations.retry-publish');
        Route::post('/operations/retry-comment', [AdminController::class, 'retryComment'])->name('operations.retry-comment');
        Route::post('/operations/settings',      [AdminController::class, 'updateOperationsSettings'])->name('operations.settings');
    });

    Route::prefix('api/v1')->group(function () {
        Route::get('/posts',               [\App\Controllers\PostController::class, 'index']);
        Route::post('/posts',              [\App\Controllers\PostController::class, 'store']);
        Route::post('/posts/schedule',     [\App\Controllers\PostController::class, 'scheduleNew']);
        Route::put('/posts/{id}',          [\App\Controllers\PostController::class, 'update']);
        Route::delete('/posts/{id}',       [\App\Controllers\PostController::class, 'destroy']);
        Route::post('/posts/{id}/schedule', [\App\Controllers\PostController::class, 'schedule']);
        Route::post('/posts/{id}/publish',  [\App\Controllers\PostController::class, 'publish']);
        Route::post('/posts/{id}/cancel',   [\App\Controllers\PostController::class, 'cancel']);
        Route::patch('/posts/{id}/reschedule', [\App\Controllers\PostController::class, 'reschedule']);
    });
});
