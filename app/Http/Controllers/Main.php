<?php

namespace App\Http\Controllers;

use App\Http\Services\BlizzApiService;
use App\Models\Item;
use Illuminate\Http\Request;
use Inertia\Inertia;

class Main extends Controller
{
    public function home(Request $request, BlizzApiService $blizzard)
    {
        $realmSlug = $request->query('realm', 'illidan');
        $search = $request->query('search');

        $category = $request->query('category', 'all');
        $subcategory = $request->query('subcategory', 'all');

        return Inertia::render('Home', [

            'realms' => fn() => $blizzard->getRealms(),

            'lastSyncedAt' => function () use ($blizzard, $realmSlug) {
                $connectedRealmId = $blizzard->getConnectedRealmId($realmSlug);

                return $blizzard->getLastSyncedAt($connectedRealmId);
            },

            /*
             * Devolvemos los filtros actuales a Vue.
             *
             * Esto permite que CategoryFilter.vue sepa qué botones
             * deben aparecer seleccionados después de una navegación.
             */
            'filters' => [
                'category' => $category,
                'subcategory' => $subcategory,
            ],

            'auctions' => function () use ($blizzard, $realmSlug, $search, $category, $subcategory) {
                set_time_limit(300);

                $blizzard->syncAuctionsToDb($realmSlug);

                $connectedRealmId = $blizzard->getConnectedRealmId(
                    $realmSlug
                );

                $listings = $blizzard->getAuctionListings(
                    $connectedRealmId,
                    $search,
                    24,
                    $category,
                    $subcategory
                );

                $listings->getCollection()->transform(function ($row) {

                    $price = BlizzApiService::copperToGsc(
                        (int) $row->min_price_copper
                    );

                    return [
                        'id' => $row->item_id,
                        'name' => $row->name,
                        'quality' => $row->quality,

                        'subtitle' => trim(
                            "{$row->item_class} · {$row->item_subclass}",
                            ' ·'
                        ),

                        'icon_url' => $row->icon_url
                            ? asset('storage/' . $row->icon_url)
                            : null,

                        'gold' => $price['gold'],
                        'silver' => $price['silver'],
                        'copper' => $price['copper'],

                        'listings' => $row->listings,
                        'volume' => $row->volume,
                    ];
                });

                return $listings;
            },
        ]);
    }
}