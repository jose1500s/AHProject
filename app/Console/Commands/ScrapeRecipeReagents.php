<?php

namespace App\Console\Commands;

use App\Http\Services\RecipeWikiScraperService;
use App\Models\Recipe;
use App\Models\RecipeReagent;
use Illuminate\Console\Command;

class ScrapeRecipeReagents extends Command
{
    protected $signature = 'recipes:scrape-reagents {--profession= : ID local de profesión, opcional}';
    protected $description = 'Completa reagents opcionales y cantidad producida de recetas scrapeando warcraft.wiki.gg';

    public function handle(RecipeWikiScraperService $scraper)
    {
        $query = Recipe::query();

        if ($professionId = $this->option('profession')) {
            $query->where('profession_id', $professionId);
        }

        $recipes = $query->whereNotNull('name_en')->get();

        if ($recipes->isEmpty()) {
            $this->warn('No hay recetas con name_en. Corre primero el comando que guarda el nombre en inglés.');
            return;
        }

        $this->info("Recetas a procesar: {$recipes->count()}");
        $this->getOutput()->progressStart($recipes->count());

        $processed = 0;
        $failed = 0;
        $quantitiesFixed = 0;

        foreach ($recipes as $recipe) {
            $url = $scraper->buildWikiUrl($recipe->name_en);
            $html = $scraper->fetchWikiHtml($url);

            if (!$html) {
                $failed++;
                $this->getOutput()->progressAdvance();
                sleep(1);
                continue;
            }

            $reagents = $scraper->parseReagents($html);
            $craftedQuantity = $scraper->parseCraftedQuantity($html);

            if ($craftedQuantity !== $recipe->produces_quantity) {
                $recipe->produces_quantity = $craftedQuantity;
                $recipe->save();
                $quantitiesFixed++;
            }

            foreach ($reagents as $r) {
                $itemIds = $scraper->resolveItemIdsByName($r['name_en']);

                if (empty($itemIds)) {
                    continue;
                }

                $lowId = $itemIds[0];
                $highId = count($itemIds) > 1 ? $itemIds[count($itemIds) - 1] : null;

                RecipeReagent::updateOrCreate(
                    [
                        'recipe_id' => $recipe->id,
                        'item_id' => $lowId,
                    ],
                    [
                        'item_id_high' => $highId,
                        'quantity' => $r['quantity'],
                        'is_optional' => $r['is_optional'],
                    ]
                );
            }

            $processed++;
            $this->getOutput()->progressAdvance();
            sleep(1);
        }

        $this->getOutput()->progressFinish();
        $this->newLine();
        $this->info("Procesadas: {$processed} | Fallidas: {$failed} | Cantidades corregidas: {$quantitiesFixed}");
    }
}