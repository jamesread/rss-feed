<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\PostViewController;
use App\Http\Controllers\StarredPostController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PostViewController::class, 'home']);
Route::get('/t/{tag}', [PostViewController::class, 'tag']);
Route::get('/starred', [PostViewController::class, 'starred']);
Route::post('/starred', [StarredPostController::class, 'store']);
Route::delete('/starred', [StarredPostController::class, 'destroy']);
Route::post('/starred/import', [StarredPostController::class, 'import']);
Route::get('/f/{feed}', [PostViewController::class, 'feed']);

Route::get('/feed', [FeedController::class, 'get']);
