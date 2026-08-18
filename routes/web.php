<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Main;
use App\Http\Controllers\ItemAuctionsController;
use App\Http\Controllers\ItemSearchController;
use App\Http\Controllers\RealmComparisonController;
use App\Http\Controllers\CommodityController;

Route::get('/', [Main::class, 'Home']);
Route::get('/items/{itemId}/auctions', [ItemAuctionsController::class, 'show']);
Route::get('/items/{itemId}/price-history', [ItemAuctionsController::class, 'priceHistory']);
Route::get('/api/realm-comparison', [RealmComparisonController::class, 'compare']);
Route::get('/api/items/search', [ItemSearchController::class, 'search']);
Route::get('/api/items/{itemId}/variants', [ItemAuctionsController::class, 'variants']);

Route::get('/api/commodities', [CommodityController::class, 'list']);
Route::get('/commodities/{itemId}/price-history', [CommodityController::class, 'priceHistory']);
Route::get('/commodities/{itemId}/item-detail', [CommodityController::class, 'itemDetail']);