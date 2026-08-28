<?php

namespace App\Http\Controllers;

use App\Http\Services\BlizzApiService;
use App\Http\Services\CraftProfitService;
use App\Models\Item;
use App\Models\Profession;
use App\Models\Recipe;
use Illuminate\Http\Request;

class BestCraftsController extends Controller
{
    public function list(Request $request, CraftProfitService $craftProfitService, BlizzApiService $blizzApiService)
    {
        $professionId = (int) $request->query('profession_id');
        $realmSlug = (string) $request->query('realm_slug');
        $quantity = max(1, (int) $request->query('quantity', 1));

        $profession = Profession::where('blizzard_id', $professionId)->first();

        if (!$profession || !$realmSlug) {
            return response()->json(['recipes' => []]);
        }

        $connectedRealmId = $blizzApiService->getConnectedRealmId($realmSlug);

        $recipeIds = Recipe::where('profession_id', $profession->id)->pluck('id');

        $results = $recipeIds
            ->map(fn($recipeId) => $craftProfitService->getRecipeProfit($recipeId, $connectedRealmId, $quantity))
            ->filter()
            ->values();

        $itemIds = collect();

        foreach ($results as $r) {
            $itemIds->push($r['produces_item_id']);
            foreach ($r['reagents'] as $reagent) {
                $itemIds->push($reagent['item_id']);
                if ($reagent['item_id_high']) {
                    $itemIds->push($reagent['item_id_high']);
                }
            }
        }

        $items = Item::whereIn('blizzard_id', $itemIds->unique())
            ->get(['blizzard_id', 'name', 'quality', 'icon_url'])
            ->keyBy('blizzard_id');

        $enriched = $results->map(function ($r) use ($items) {
            $produced = $items->get($r['produces_item_id']);
            $r['produces_name'] = $produced->name ?? 'Desconocido';
            $r['produces_quality'] = $produced->quality ?? 'common';
            $r['produces_icon_url'] = $produced->icon_url ?? null;

            $r['reagents'] = collect($r['reagents'])->map(function ($reagent) use ($items) {
                $item = $items->get($reagent['item_id']);
                $reagent['name'] = $item->name ?? 'Desconocido';
                $reagent['icon_url'] = $item->icon_url ?? null;
                return $reagent;
            })->all();

            return $r;
        })->values();

        return response()->json(['recipes' => $enriched]);
    }

    public function professions()
    {
        $professions = Profession::whereIn('blizzard_id', [164, 333, 197, 773, 165, 755, 202, 171])
            ->orderBy('name')
            ->get(['id', 'blizzard_id', 'name']);

        return response()->json(['professions' => $professions]);
    }
}