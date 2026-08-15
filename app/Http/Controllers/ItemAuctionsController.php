<?php

namespace App\Http\Controllers;

use App\Http\Services\BlizzApiService;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemAuctionsController extends Controller
{
    public function show(int $itemId, Request $request, BlizzApiService $blizzard)
    {
        $realmSlug = $request->query('realm', 'illidan');
        $connectedRealmId = $blizzard->getConnectedRealmId($realmSlug);

        $item = Item::where('blizzard_id', $itemId)->first();
        $realm = collect($blizzard->getRealms())->firstWhere('slug', $realmSlug);

        return response()->json([
            'item' => $item ? [
                'name' => $item->name,
                'quality' => $item->quality,
                'icon_url' => $item->icon_url, // <- ya viene completo desde el accessor del modelo
            ] : null,
            'realm_name' => $realm['name'] ?? $realmSlug,
            'rows' => $blizzard->getItemAuctionBreakdown($connectedRealmId, $itemId),
        ]);
    }
}