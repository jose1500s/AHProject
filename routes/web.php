<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Main;
use App\Http\Controllers\ItemAuctionsController;
use App\Http\Controllers\ItemSearchController;
use App\Http\Controllers\RealmComparisonController;
use App\Http\Controllers\CommodityController;
use App\Http\Controllers\WowSyncController;
use App\Http\Controllers\WowDashboardController;
use App\Http\Controllers\BestCraftsController;

Route::get('/', [Main::class, 'Home']);
Route::get('/items/{itemId}/auctions', [ItemAuctionsController::class, 'show']);
Route::get('/items/{itemId}/price-history', [ItemAuctionsController::class, 'priceHistory']);
Route::get('/api/realm-comparison', [RealmComparisonController::class, 'compare']);
Route::get('/api/items/search', [ItemSearchController::class, 'search']);
Route::get('/api/items/{itemId}/variants', [ItemAuctionsController::class, 'variants']);

Route::get('/api/commodities', [CommodityController::class, 'list']);
Route::post('/api/commodities/sync', [CommodityController::class, 'sync']);
Route::get('/commodities/{itemId}/price-history', [CommodityController::class, 'priceHistory']);
Route::get('/commodities/{itemId}/item-detail', [CommodityController::class, 'itemDetail']);
Route::post('/api/wow-sync', [WowSyncController::class, 'ingest']);

Route::get('/api/wow/characters', [WowDashboardController::class, 'characters']);
Route::get('/api/wow/overview', [WowDashboardController::class, 'overview']);
Route::get('/api/wow/active-auctions', [WowDashboardController::class, 'activeAuctions']);
Route::get('/api/wow/transactions', [WowDashboardController::class, 'transactions']);
Route::get('/api/wow/sales-by-item', [WowDashboardController::class, 'salesByItem']);

Route::get('/api/crafts', [BestCraftsController::class, 'list']);
Route::get('/api/professions', [BestCraftsController::class, 'professions']);