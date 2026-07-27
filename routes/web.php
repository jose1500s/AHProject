<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Main;

Route::get("/", [Main::class, 'Home']);
