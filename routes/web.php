<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Main;
use App\Http\Controllers\ItemAuctionsController;
use App\Http\Controllers\ItemSearchController;
use App\Http\Controllers\RealmComparisonController;

Route::get('/', [Main::class, 'Home']);
Route::get('/items/{itemId}/auctions', [ItemAuctionsController::class, 'show']);
Route::get('/api/realm-comparison', [RealmComparisonController::class, 'compare']);
Route::get('/api/items/search', [ItemSearchController::class, 'search']);
Route::get('/api/items/{itemId}/variants', [ItemAuctionsController::class, 'variants']);