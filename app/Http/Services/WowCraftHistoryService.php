<?php

namespace App\Http\Services;

use App\Models\Recipe;
use App\Models\WowCraftHistory;
use Illuminate\Support\Carbon;

class WowCraftHistoryService
{
    public function __construct(
        protected CraftProfitService $craftProfitService,
        protected BlizzApiService $blizzApiService
    ) {
    }

    public function getHistory(
        string $characterKey,
        Carbon $from,
        Carbon $to,
        string $realmSlug
    ): array {
        $connectedRealmId = $this->blizzApiService->getConnectedRealmId($realmSlug);

        $crafts = WowCraftHistory::where('character_key', $characterKey)
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->get();

        $recipesByProducesId = Recipe::whereNotNull('produces_item_id')
            ->get()
            ->keyBy('produces_item_id');

        $recipesByProducesHighId = Recipe::whereNotNull('produces_item_id_high')
            ->get()
            ->keyBy('produces_item_id_high');

        $totalConcentrationSpent = 0;
        $totalRevenue = 0;
        $totalCost = 0;
        $entries = [];

        foreach ($crafts as $craft) {
            $recipe = $recipesByProducesId->get($craft->item_id)
                ?? $recipesByProducesHighId->get($craft->item_id);

            $totalConcentrationSpent += $craft->concentration_spent;

            if (!$recipe) {
                $entries[] = [
                    'craft_id' => $craft->id,
                    'item_id' => $craft->item_id,
                    'item_name' => null,
                    'icon_url' => null,
                    'quantity' => $craft->quantity,
                    'multicraft' => $craft->multicraft,
                    'concentration_spent' => $craft->concentration_spent,
                    'crafting_quality' => $craft->crafting_quality,
                    'occurred_at' => $craft->occurred_at->toIso8601String(),
                    'revenue_copper' => 0,
                    'cost_copper' => 0,
                    'profit_copper' => 0,
                    'resolved' => false,
                ];
                continue;
            }

            $isHighVariant = $recipe->produces_item_id_high === $craft->item_id;

            $qty = max(1, (int) floor($craft->quantity / max(1, $recipe->produces_quantity)));

            $profit = $this->craftProfitService->getRecipeProfit($recipe->id, $connectedRealmId, $qty);

            $sellUnit = $isHighVariant
                ? $profit['sell_unit_high_copper']
                : $profit['sell_unit_low_copper'];

            $revenue = $sellUnit * $craft->quantity;

            $cost = collect($profit['reagents'])->sum(function ($r) use ($isHighVariant) {
                $unit = $isHighVariant ? $r['unit_price_high_copper'] : $r['unit_price_low_copper'];
                return $unit * $r['quantity_needed'];
            });

            $craftProfit = $revenue - $cost;

            $totalRevenue += $revenue;
            $totalCost += $cost;

            $entries[] = [
                'craft_id' => $craft->id,
                'recipe_id' => $recipe->id,
                'item_id' => $craft->item_id,
                'item_name' => $recipe->name,
                'icon_url' => $recipe->icon_url,
                'quantity' => $craft->quantity,
                'multicraft' => $craft->multicraft,
                'concentration_spent' => $craft->concentration_spent,
                'crafting_quality' => $craft->crafting_quality,
                'occurred_at' => $craft->occurred_at->toIso8601String(),
                'revenue_copper' => $revenue,
                'cost_copper' => $cost,
                'profit_copper' => $craftProfit,
                'resolved' => true,
            ];
        }

        return [
            'entries' => $entries,
            'summary' => [
                'concentration_spent' => $totalConcentrationSpent,
                'revenue_copper' => $totalRevenue,
                'cost_copper' => $totalCost,
                'profit_copper' => $totalRevenue - $totalCost,
                'crafts_count' => $crafts->count(),
            ],
        ];
    }
}