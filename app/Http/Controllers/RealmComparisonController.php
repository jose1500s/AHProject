<?php

namespace App\Http\Controllers;

use App\Http\Services\BlizzApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RealmComparisonController extends Controller
{
    public function compare(Request $request, BlizzApiService $blizzard)
    {
        $items = json_decode($request->query('items', '[]'), true) ?: [];
        $realmSlugs = $request->query('realm_slugs', []);
        $force = $request->boolean('force');

        if (empty($items) || empty($realmSlugs)) {
            return response()->json(['items' => [], 'last_synced' => []]);
        }

        return response()->json($blizzard->getRealmPriceComparison($items, $realmSlugs, $force));
    }
}