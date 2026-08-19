<?php

namespace App\Http\Controllers;

use App\Http\Services\BlizzApiService;
use App\Models\Item;
use Illuminate\Http\Request;

class CommodityController extends Controller
{
    public function list(Request $request, BlizzApiService $blizzard)
    {
        $search = $request->query('search');

        $listings = $blizzard->getCommodityListings($search);

        $listings->getCollection()->transform(function ($row) {
            $price = BlizzApiService::copperToGsc((int) $row->min_price_copper);

            return [
                'id' => $row->item_id,
                'name' => $row->name,
                'quality' => $row->quality,
                'subtitle' => trim("{$row->item_class} · {$row->item_subclass}", ' ·'),
                'icon_url' => $row->icon_url ? asset('storage/' . $row->icon_url) : null,
                'gold' => $price['gold'],
                'silver' => $price['silver'],
                'copper' => $price['copper'],
                'listings' => $row->listings,
                'volume' => $row->volume,
            ];
        });

        return response()->json([
            'commodities' => $listings,
            'lastSyncedAt' => $blizzard->getCommodityLastSyncedAt(),
        ]);
    }

    public function sync(BlizzApiService $blizzard)
    {
        $result = $blizzard->syncCommoditiesToDb(true);

        return response()->json([
            'ok' => true,
            'updated' => $result['updated'] ?? false,
            'lastSyncedAt' => $blizzard->getCommodityLastSyncedAt(),
        ]);
    }

    public function priceHistory(int $itemId, Request $request, BlizzApiService $blizzard)
    {
        $days = (int) $request->query('days', 30);

        return response()->json([
            'history' => $blizzard->getCommodityPriceHistory($itemId, $days),
        ]);
    }

    public function itemDetail(int $itemId)
    {
        $item = Item::where('blizzard_id', $itemId)->first();

        return response()->json([
            'item' => $item ? [
                'name' => $item->name,
                'quality' => $item->quality,
                'icon_url' => $item->icon_url,
            ] : null,
        ]);
    }
}