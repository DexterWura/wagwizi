<?php

use App\Controllers\CronController;
use App\Controllers\PostController;
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

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (\Illuminate\Http\Request $request) {
        return $request->user();
    });

    Route::post('/posts',              [PostController::class, 'store']);
    Route::get('/posts',               [PostController::class, 'index']);
    Route::put('/posts/{id}',          [PostController::class, 'update']);
    Route::delete('/posts/{id}',       [PostController::class, 'destroy']);
    Route::post('/posts/{id}/schedule', [PostController::class, 'schedule']);
    Route::post('/posts/{id}/publish',  [PostController::class, 'publish']);
    Route::post('/posts/{id}/cancel',   [PostController::class, 'cancel']);
    Route::patch('/posts/{id}/reschedule', [PostController::class, 'reschedule']);

});
