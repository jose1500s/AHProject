<?php

namespace App\Http\Services;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\WowCharacter;
use App\Models\WowCraftHistory;
use Illuminate\Support\Carbon;

class WowCraftHistoryService
{
    public function __construct(
        protected CraftProfitService $craftProfitService,
        protected BlizzApiService $blizzApiService
    ) {
    }

    public function getHistoryAll(
        Carbon $from,
        Carbon $to,
        string $realmSlug
    ): array {
        $connectedRealmId = $this->blizzApiService->getConnectedRealmId($realmSlug);

        $crafts = WowCraftHistory::whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->get();

        $characterNames = WowCharacter::pluck('name', 'character_key');

        $recipesByProducesId = Recipe::with('profession')
            ->whereNotNull('produces_item_id')
            ->get()
            ->keyBy('produces_item_id');

        $recipesByProducesHighId = Recipe::with('profession')
            ->whereNotNull('produces_item_id_high')
            ->get()
            ->keyBy('produces_item_id_high');

        $itemIcons = Item::whereIn('blizzard_id', $crafts->pluck('item_id')->unique())
            ->pluck('icon_url', 'blizzard_id');

        $totalConcentrationSpent = 0;
        $totalRevenue = 0;
        $entries = [];

        foreach ($crafts as $craft) {
            $recipe = $recipesByProducesId->get($craft->item_id)
                ?? $recipesByProducesHighId->get($craft->item_id);

            $totalConcentrationSpent += $craft->concentration_spent;

            [$realm, $charName] = array_pad(explode('-', $craft->character_key, 2), 2, null);
            $characterName = $characterNames->get($craft->character_key) ?? $charName ?? $craft->character_key;

            $itemIconUrl = $itemIcons->get($craft->item_id);

            if (!$recipe) {
                $entries[] = [
                    'craft_id' => $craft->id,
                    'character_key' => $craft->character_key,
                    'character_name' => $characterName,
                    'item_id' => $craft->item_id,
                    'item_name' => null,
                    'icon_url' => $itemIconUrl,
                    'profession' => null,
                    'quantity' => $craft->quantity,
                    'multicraft' => $craft->multicraft,
                    'concentration_spent' => $craft->concentration_spent,
                    'crafting_quality' => $craft->crafting_quality,
                    'occurred_at' => $craft->occurred_at->toIso8601String(),
                    'revenue_copper' => 0,
                    'resolved' => false,
                ];
                continue;
            }

            $isHighVariant = $craft->crafting_quality >= 2 && $recipe->produces_item_id_high !== null;

            $qty = max(1, (int) floor($craft->quantity / max(1, $recipe->produces_quantity)));

            $profit = $this->craftProfitService->getRecipeProfit($recipe->id, $connectedRealmId, $qty);

            $sellUnit = $isHighVariant
                ? $profit['sell_unit_high_copper']
                : $profit['sell_unit_low_copper'];

            $revenue = $sellUnit * $craft->quantity;

            $totalRevenue += $revenue;

            $entries[] = [
                'craft_id' => $craft->id,
                'character_key' => $craft->character_key,
                'character_name' => $characterName,
                'recipe_id' => $recipe->id,
                'item_id' => $craft->item_id,
                'item_name' => $recipe->name,
                'icon_url' => $itemIconUrl ?? $recipe->icon_url,
                'profession' => $recipe->profession->name ?? null,
                'quantity' => $craft->quantity,
                'multicraft' => $craft->multicraft,
                'concentration_spent' => $craft->concentration_spent,
                'crafting_quality' => $craft->crafting_quality,
                'occurred_at' => $craft->occurred_at->toIso8601String(),
                'revenue_copper' => $revenue,
                'resolved' => true,
            ];
        }

        return [
            'entries' => $entries,
            'summary' => [
                'concentration_spent' => $totalConcentrationSpent,
                'revenue_copper' => $totalRevenue,
                'crafts_count' => $crafts->count(),
            ],
        ];
    }
}