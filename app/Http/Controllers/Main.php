<?php

namespace App\Http\Controllers;
use App\Http\Services\BlizzApiService;

use Illuminate\Http\Request;
use Inertia\Inertia;

class Main extends Controller
{
    public function home(Request $request, BlizzApiService $blizzard)
    {
        return Inertia::render('Home', [
            'realms' => fn() => $blizzard->getRealms(),
        ]);
    }
}
