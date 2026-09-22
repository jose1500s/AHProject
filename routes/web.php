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
use App\Http\Controllers\WowCraftHistoryController;
use App\Http\Controllers\WowChecklistController;
use App\Http\Controllers\CommodityWatchlistController;
use App\Http\Controllers\FarmSessionController;

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
Route::get('/api/wow/craft-history', [WowCraftHistoryController::class, 'index']);
Route::get('/api/wow/checklist', [WowChecklistController::class, 'overview']);
Route::get('/api/wow/checklist/concentration', [WowChecklistController::class, 'concentrationAll']);
Route::get('/api/wow/checklist/summary', [WowChecklistController::class, 'summary']);
Route::delete('/api/wow/craft-history/{id}', [WowCraftHistoryController::class, 'destroy']);

Route::get('/api/commodities/watchlist', [CommodityWatchlistController::class, 'index']);
Route::get('/api/commodities/watchlist/search', [CommodityWatchlistController::class, 'search']);
Route::post('/api/commodities/watchlist', [CommodityWatchlistController::class, 'store']);
Route::delete('/api/commodities/watchlist/{id}', [CommodityWatchlistController::class, 'destroy']);
Route::get('/api/commodities/{itemId}/buy-estimate', [CommodityWatchlistController::class, 'buyEstimate']);
Route::get('/api/commodities/{itemId}/profit-ladder', [CommodityWatchlistController::class, 'profitLadder']);

Route::get('/api/farm-sessions/summary', [FarmSessionController::class, 'summary']);
Route::get('/api/farm-sessions/analytics', [FarmSessionController::class, 'analytics']);
Route::get('/api/farm-sessions/detected-character', [FarmSessionController::class, 'detectedCharacter']);
Route::post('/api/farm-sessions', [FarmSessionController::class, 'store']);
Route::patch('/api/farm-sessions/{id}/stop', [FarmSessionController::class, 'stop']);
Route::get('/api/farm-sessions', [FarmSessionController::class, 'index']);
Route::get('/api/farm-sessions/{id}', [FarmSessionController::class, 'show']);
Route::get('/api/farm-sessions/{id}/stats', [FarmSessionController::class, 'stats']);
Route::delete('/api/farm-sessions/{id}', [FarmSessionController::class, 'destroy']);
Route::get('/api/wow/gold-history', [WowDashboardController::class, 'goldHistory']);
Route::get('/api/wow/gold-goal', [WowDashboardController::class, 'goldGoal']);
Route::post('/api/wow/gold-goal', [WowDashboardController::class, 'updateGoldGoal']);