<?php

use App\Controllers\ComposerMentionController;
use App\Controllers\CronController;
use App\Controllers\PostController;
use App\Controllers\ProfileController;
use App\Controllers\WorkflowController;
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
    Route::post('/posts/{id}/retry-failed-platforms', [PostController::class, 'retryFailedPlatforms'])->whereNumber('id');
    Route::get('/posts/{id}/publish-summary', [PostController::class, 'publishSummary'])->whereNumber('id');
    Route::post('/posts/{id}/cancel',   [PostController::class, 'cancel']);
    Route::patch('/posts/{id}/reschedule', [PostController::class, 'reschedule']);

    Route::get('/composer/mentions', [ComposerMentionController::class, 'index'])
        ->middleware('throttle:120,1');

    Route::middleware('plan_workflow')->group(function () {
        Route::get('/workflows', [WorkflowController::class, 'index']);
        Route::post('/workflows', [WorkflowController::class, 'store']);
        Route::get('/workflows/templates', [WorkflowController::class, 'templates']);
        Route::get('/workflows/{id}', [WorkflowController::class, 'show'])->whereNumber('id');
        Route::put('/workflows/{id}', [WorkflowController::class, 'update'])->whereNumber('id');
        Route::delete('/workflows/{id}', [WorkflowController::class, 'destroy'])->whereNumber('id');
        Route::post('/workflows/{id}/run', [WorkflowController::class, 'run'])->whereNumber('id');
        Route::get('/workflows/{id}/runs', [WorkflowController::class, 'runs'])->whereNumber('id');
        Route::post('/workflows/events/{eventKey}', [WorkflowController::class, 'triggerEvent']);
    });

});
