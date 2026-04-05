<?php

use App\Controllers\ComposerMentionController;
use App\Controllers\CronController;
use App\Controllers\PostController;
use App\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cron endpoint (token-guarded, no session/auth middleware)
|--------------------------------------------------------------------------
*/

Route::post('/cron/run', [CronController::class, 'run'])->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| Authenticated API routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->group(function () {

    Route::get('/user', [ProfileController::class, 'currentUser']);

    Route::post('/posts',              [PostController::class, 'store']);
    Route::get('/posts',               [PostController::class, 'index']);
    Route::get('/posts/{id}',          [PostController::class, 'show'])->whereNumber('id');
    Route::post('/posts/schedule',     [PostController::class, 'scheduleNew']);
    Route::put('/posts/{id}',          [PostController::class, 'update']);
    Route::delete('/posts/{id}',       [PostController::class, 'destroy']);
    Route::post('/posts/{id}/schedule', [PostController::class, 'schedule']);
    Route::post('/posts/{id}/publish',  [PostController::class, 'publish']);
    Route::get('/posts/{id}/publish-summary', [PostController::class, 'publishSummary'])->whereNumber('id');
    Route::post('/posts/{id}/cancel',   [PostController::class, 'cancel']);
    Route::patch('/posts/{id}/reschedule', [PostController::class, 'reschedule']);

    Route::get('/composer/mentions', [ComposerMentionController::class, 'index'])
        ->middleware('throttle:120,1');

});
