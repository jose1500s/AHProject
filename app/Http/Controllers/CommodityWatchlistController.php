<?php

namespace App\Http\Controllers;

use App\Http\Services\CommodityMarketAnalysisService;
use App\Models\CommodityPriceHistory;
use App\Models\CommodityWatchlist;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\RecipeReagent;
use Illuminate\Http\Request;

class CommodityWatchlistController extends Controller
{
    public function __construct(
        protected CommodityMarketAnalysisService $analysisService
    ) {
    }

    private function formatCoin(int $copper): array
    {
        return [
            'gold' => intdiv($copper, 10000),
            'silver' => intdiv($copper % 10000, 100),
            'copper' => $copper % 100,
        ];
    }

    private function resolveCraftQualities(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $goldFromRecipes = Recipe::whereIn('produces_item_id_high', $itemIds)
            ->pluck('produces_item_id_high')->all();

        $silverFromRecipes = Recipe::whereIn('produces_item_id', $itemIds)
            ->whereNotNull('produces_item_id_high')
            ->pluck('produces_item_id')->all();

        $goldFromReagents = RecipeReagent::whereIn('item_id_high', $itemIds)
            ->pluck('item_id_high')->all();

        $silverFromReagents = RecipeReagent::whereIn('item_id', $itemIds)
            ->whereNotNull('item_id_high')
            ->pluck('item_id')->all();

        $goldIds = array_unique(array_merge($goldFromRecipes, $goldFromReagents));
        $silverIds = array_unique(array_merge($silverFromRecipes, $silverFromReagents));

        $map = [];
        foreach ($itemIds as $id) {
            if (in_array($id, $goldIds)) {
                $map[$id] = 'gold';
            } elseif (in_array($id, $silverIds)) {
                $map[$id] = 'silver';
            } else {
                $map[$id] = null;
            }
        }

        return $map;
    }

    public function index()
    {
        $watchlist = CommodityWatchlist::where('enabled', true)->orderByDesc('created_at')->get();
        $followedItemIds = $watchlist->pluck('item_id')->all();

        $recommendationsRaw = $this->analysisService->getRecommendations(50, $followedItemIds);
        $reboundsRaw = $this->analysisService->getRebounds(50, $followedItemIds);
        $breakevenDropsRaw = $this->analysisService->getBreakevenDrops(50, $followedItemIds);

        $allItemIds = array_unique(array_merge(
            $followedItemIds,
            collect($recommendationsRaw)->pluck('item_id')->all(),
            collect($reboundsRaw)->pluck('item_id')->all(),
            collect($breakevenDropsRaw)->pluck('item_id')->all()
        ));

        $items = Item::whereIn('blizzard_id', $allItemIds)->get()->keyBy('blizzard_id');
        $craftQualities = $this->resolveCraftQualities($allItemIds);

        $watchlistOut = $watchlist->map(function ($w) use ($items, $craftQualities) {
            $item = $items->get($w->item_id);
            $stats = $this->analysisService->getStats($w->item_id);

            if (!$stats) {
                return [
                    'id' => $w->id,
                    'item_id' => $w->item_id,
                    'item_name' => $item?->name ?? "Ítem #{$w->item_id}",
                    'icon_url' => $item?->icon_url,
                    'craft_quality' => $craftQualities[$w->item_id] ?? null,
                    'has_data' => false,
                ];
            }

            return [
                'id' => $w->id,
                'item_id' => $w->item_id,
                'item_name' => $item?->name ?? "Ítem #{$w->item_id}",
                'icon_url' => $item?->icon_url,
                'craft_quality' => $craftQualities[$w->item_id] ?? null,
                'has_data' => true,
                'current' => $this->formatCoin($stats['current_price_copper']),
                'current_copper' => $stats['current_price_copper'],
                'median' => $this->formatCoin($stats['median_price_copper']),
                'median_copper' => $stats['median_price_copper'],
                'projection_max' => $this->formatCoin($stats['projection_max_copper']),
                'projection_max_copper' => $stats['projection_max_copper'],
                'projection_min' => $this->formatCoin($stats['projection_min_copper']),
                'projection_min_copper' => $stats['projection_min_copper'],
                'trend' => $stats['trend'],
                'percent_change_vs_yesterday' => $stats['percent_change_vs_yesterday'],
            ];
        });

        $recommendationsOut = collect($recommendationsRaw)->map(function ($r) use ($items, $craftQualities) {
            $item = $items->get($r['item_id']);

            return [
                'item_id' => $r['item_id'],
                'item_name' => $item?->name ?? "Ítem #{$r['item_id']}",
                'icon_url' => $item?->icon_url,
                'craft_quality' => $craftQualities[$r['item_id']] ?? null,
                'current' => $this->formatCoin($r['current_price_copper']),
                'discount_percent' => $r['discount_percent'],
                'reason' => $r['reason'],
            ];
        });

        $reboundsOut = collect($reboundsRaw)->map(function ($r) use ($items, $craftQualities) {
            $item = $items->get($r['item_id']);

            return [
                'item_id' => $r['item_id'],
                'item_name' => $item?->name ?? "Ítem #{$r['item_id']}",
                'icon_url' => $item?->icon_url,
                'craft_quality' => $craftQualities[$r['item_id']] ?? null,
                'current' => $this->formatCoin($r['current_price_copper']),
                'recent_min' => $this->formatCoin($r['recent_min_copper']),
                'rebound_percent' => $r['rebound_percent'],
                'reason' => $r['reason'],
            ];
        });

        $breakevenDropsOut = collect($breakevenDropsRaw)->map(function ($r) use ($items, $craftQualities) {
            $item = $items->get($r['item_id']);

            return [
                'item_id' => $r['item_id'],
                'item_name' => $item?->name ?? "Ítem #{$r['item_id']}",
                'icon_url' => $item?->icon_url,
                'craft_quality' => $craftQualities[$r['item_id']] ?? null,
                'current' => $this->formatCoin($r['current_price_copper']),
                'previous' => $this->formatCoin($r['previous_price_copper']),
                'drop_percent' => $r['drop_percent'],
                'reason' => $r['reason'],
            ];
        });

        return response()->json([
            'watchlist' => $watchlistOut,
            'recommendations' => $recommendationsOut,
            'rebounds' => $reboundsOut,
            'breakeven_drops' => $breakevenDropsOut,
            'breakeven_percent' => $this->analysisService->breakevenPercent(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|integer',
        ]);

        $entry = CommodityWatchlist::updateOrCreate(
            ['item_id' => $data['item_id']],
            ['enabled' => true]
        );

        return response()->json(['ok' => true, 'id' => $entry->id]);
    }

    public function destroy(int $id)
    {
        CommodityWatchlist::where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    public function search(Request $request)
    {
        $query = (string) $request->query('q', '');

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $commodityItemIds = CommodityPriceHistory::distinct()->pluck('item_id');

        $items = Item::whereIn('blizzard_id', $commodityItemIds)
            ->where('name', 'ilike', "%{$query}%")
            ->limit(15)
            ->get(['blizzard_id', 'name', 'icon_url', 'quality']);

        $itemIds = $items->pluck('blizzard_id')->all();
        $craftQualities = $this->resolveCraftQualities($itemIds);

        $latestPrices = \App\Models\CommodityAuction::whereIn('item_id', $itemIds)
            ->selectRaw('item_id, MIN(unit_price) as min_price')
            ->groupBy('item_id')
            ->pluck('min_price', 'item_id');

        $results = $items->map(function ($item) use ($craftQualities, $latestPrices) {
            $price = $latestPrices->get($item->blizzard_id);

            return [
                'blizzard_id' => $item->blizzard_id,
                'name' => $item->name,
                'icon_url' => $item->icon_url,
                'craft_quality' => $craftQualities[$item->blizzard_id] ?? null,
                'current_price' => $price ? $this->formatCoin((int) $price) : null,
            ];
        });

        return response()->json(['results' => $results]);
    }

    public function buyEstimate(Request $request, int $itemId)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $estimate = $this->analysisService->estimatePurchase($itemId, $data['quantity']);

        return response()->json($estimate);
    }

    public function profitLadder(Request $request, int $itemId)
    {
        $data = $request->validate([
            'unit_price' => 'nullable|integer|min:1',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $quantity = $data['quantity'] ?? 1;
        $unitPrice = $data['unit_price'] ?? null;

        if (!$unitPrice) {
            $stats = $this->analysisService->getStats($itemId);
            $unitPrice = $stats['current_price_copper'] ?? null;
        }

        if (!$unitPrice) {
            return response()->json(['ladder' => [], 'unit_price' => null, 'total_paid' => null, 'breakeven_percent' => $this->analysisService->breakevenPercent()]);
        }

        $ladder = $this->analysisService->buildProfitLadder($unitPrice, quantity: $quantity);

        $ladderOut = collect($ladder)->map(fn($row) => [
            'step_gold' => $row['step_gold'],
            'sell_price' => $this->formatCoin($row['sell_price_copper']),
            'total_sale' => $this->formatCoin($row['total_sale_copper']),
            'profit' => $this->formatCoin(abs($row['profit_copper'])),
            'is_profit' => $row['profit_copper'] >= 0,
        ]);

        return response()->json([
            'ladder' => $ladderOut,
            'unit_price' => $this->formatCoin($unitPrice),
            'quantity' => $quantity,
            'total_paid' => $this->formatCoin($unitPrice * $quantity),
            'breakeven_percent' => $this->analysisService->breakevenPercent(),
        ]);
    }
}