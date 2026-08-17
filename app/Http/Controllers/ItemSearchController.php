<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class ItemSearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim($request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $items = Item::where('name', 'ilike', "%{$q}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['blizzard_id', 'name', 'quality', 'icon_url']);

        return response()->json($items);
    }
}