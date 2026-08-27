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
                'icon_url' => $item->icon_url,
            ] : null,
            'realm_name' => $realm['name'] ?? $realmSlug,
            'rows' => $blizzard->getItemAuctionBreakdown($connectedRealmId, $itemId),
        ]);
    }

    public function variants(int $itemId, BlizzApiService $blizzard)
    {
        $item = \App\Models\Item::where('blizzard_id', $itemId)->first();

        return response()->json([
            'item' => $item ? [
                'name' => $item->name,
                'quality' => $item->quality,
                'icon_url' => $item->icon_url,
            ] : null,
            'variants' => $blizzard->getItemIlvlVariants($itemId),
        ]);
    }


    public function priceHistory(int $itemId, Request $request, BlizzApiService $blizzard)
    {
        $realmSlug = $request->query('realm', 'illidan');
        $ilvl = $request->query('ilvl');
        $days = (int) $request->query('days', 30);

        $connectedRealmId = $blizzard->getConnectedRealmId($realmSlug);

        return response()->json([
            'history' => $blizzard->getItemPriceHistory(
                $connectedRealmId,
                $itemId,
                $ilvl !== null ? (int) $ilvl : null,
                $days
            ),
        ]);
    }
}