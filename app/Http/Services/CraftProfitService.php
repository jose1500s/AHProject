<?php

namespace App\Http\Services;

use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

class CraftProfitService
{
    public function __construct(
        protected BlizzApiService $blizzApiService
    ) {
    }

    public function getRecipeProfit(
        int $recipeId,
        int $connectedRealmId,
        int $quantity = 1
    ): ?array {
        $recipe = Recipe::with('reagents')->find($recipeId);

        if (!$recipe) {
            return null;
        }

        $reagentDetails = $recipe->reagents->map(function ($reagent) use ($quantity, $connectedRealmId) {
            $neededQuantity = $reagent->quantity * $quantity;

            $priceA = $this->resolveItemPrice($reagent->item_id, $connectedRealmId);
            $priceB = $reagent->item_id_high
                ? $this->resolveItemPrice($reagent->item_id_high, $connectedRealmId)
                : $priceA;

            $copperA = $priceA['price_copper'] ?? 0;
            $copperB = $priceB['price_copper'] ?? 0;

            $low = min($copperA, $copperB);
            $high = max($copperA, $copperB);

            return [
                'item_id' => $reagent->item_id,
                'item_id_high' => $reagent->item_id_high,
                'quantity_per_craft' => $reagent->quantity,
                'quantity_needed' => $neededQuantity,
                'is_optional' => $reagent->is_optional,
                'unit_price_low_copper' => $low,
                'unit_price_high_copper' => $high,
                'source' => $priceA['source'] ?? null,
            ];
        });

        $sellPriceA = $this->resolveItemPrice($recipe->produces_item_id, $connectedRealmId);
        $sellPriceB = $recipe->produces_item_id_high
            ? $this->resolveItemPrice($recipe->produces_item_id_high, $connectedRealmId)
            : $sellPriceA;

        $sellCopperA = $sellPriceA['price_copper'] ?? 0;
        $sellCopperB = $sellPriceB['price_copper'] ?? 0;

        $sellLow = min($sellCopperA, $sellCopperB);
        $sellHigh = max($sellCopperA, $sellCopperB);

        $totalProduced = $recipe->produces_quantity * $quantity;

        return [
            'recipe_id' => $recipe->id,
            'name' => $recipe->name,
            'icon_url' => $recipe->icon_url,
            'produces_item_id' => $recipe->produces_item_id,
            'produces_item_id_high' => $recipe->produces_item_id_high,
            'produces_quantity' => $totalProduced,
            'quantity' => $quantity,
            'reagents' => $reagentDetails->values()->all(),
            'sell_unit_low_copper' => $sellLow,
            'sell_unit_high_copper' => $sellHigh,
        ];
    }

    protected function resolveItemPrice(?int $itemId, int $connectedRealmId): ?array
    {
        if ($itemId === null) {
            return null;
        }

        $commodityPrice = DB::table('commodity_auctions')
            ->where('item_id', $itemId)
            ->selectRaw('MIN(unit_price) as min_price')
            ->value('min_price');

        if ($commodityPrice !== null) {
            return ['price_copper' => (int) $commodityPrice, 'source' => 'commodity'];
        }

        $auctionPrice = DB::table('auctions')
            ->where('connected_realm_id', $connectedRealmId)
            ->where('item_id', $itemId)
            ->whereNotNull(DB::raw('COALESCE(buyout, unit_price)'))
            ->selectRaw('MIN(COALESCE(buyout, unit_price)) as min_price')
            ->value('min_price');

        if ($auctionPrice !== null) {
            return ['price_copper' => (int) $auctionPrice, 'source' => 'auction'];
        }

        return ['price_copper' => 0, 'source' => 'unavailable'];
    }
}