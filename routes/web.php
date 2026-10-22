<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\PostViewController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StarredFeedController;
use App\Http\Controllers\StarredPostController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PostViewController::class, 'home']);
Route::get('/t/{tag}', [PostViewController::class, 'tag']);
Route::get('/starred', [PostViewController::class, 'starred']);
Route::post('/starred', [StarredPostController::class, 'store']);
Route::delete('/starred', [StarredPostController::class, 'destroy']);
Route::post('/starred/import', [StarredPostController::class, 'import']);
Route::get('/favorites', StarredFeedController::class);
Route::get('/settings/{section?}', [SettingsController::class, 'show'])->whereIn('section', ['general', 'feeds']);
Route::put('/settings', [SettingsController::class, 'update']);
Route::put('/settings/feeds', [SettingsController::class, 'updateFeeds']);
Route::get('/f/{feed}', [PostViewController::class, 'feed']);

Route::get('/feed', [FeedController::class, 'get']);
