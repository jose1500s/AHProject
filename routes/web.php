<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Main;
use App\Http\Controllers\ItemAuctionsController;

Route::get("/", [Main::class, 'Home']);
Route::get('/items/{itemId}/auctions', [ItemAuctionsController::class, 'show']);