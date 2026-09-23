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
            return response()->json([
                'items' => [],
                'last_synced' => [],
            ]);
        }

        /*
         * Normalizamos los items recibidos.
         *
         * En el addon usamos itemLevel = 1 para representar
         * items que realmente no tienen un ilvl útil.
         *
         * Para Realm Comparison:
         *
         *     ilvl = 1     -> null
         *     ilvl = null  -> null
         *     ilvl > 1     -> se conserva
         *
         * De esta manera el resto del sistema solamente
         * necesita trabajar con null cuando el item no tiene ilvl.
         */
        $items = collect($items)
            ->filter(function ($item) {
                return isset($item['item_id']);
            })
            ->map(function ($item) {
                $rawIlvl = $item['ilvl'] ?? null;

                if (
                    $rawIlvl === null ||
                    $rawIlvl === '' ||
                    (int) $rawIlvl === 1
                ) {
                    $ilvl = null;
                } else {
                    $ilvl = (int) $rawIlvl;
                }

                return [
                    'item_id' => (int) $item['item_id'],
                    'ilvl' => $ilvl,
                ];
            })
            ->values()
            ->all();

        if (empty($items)) {
            return response()->json([
                'items' => [],
                'last_synced' => [],
            ]);
        }

        return response()->json(
            $blizzard->getRealmPriceComparison(
                $items,
                $realmSlugs,
                $force
            )
        );
    }
}