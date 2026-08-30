<?php

namespace App\Console\Commands;

use App\Http\Services\ProfessionApiService;
use App\Http\Services\RecipeWikiScraperService;
use App\Models\Profession;
use App\Models\Recipe;
use App\Models\RecipeReagent;
use Illuminate\Console\Command;

class SyncAllProfessionRecipes extends Command
{
    protected $signature = 'professions:sync-recipes-all';
    protected $description = 'Sincroniza recetas + scrapea reagents opcionales para todas las profesiones craftables definidas';

    protected array $professionIds = [171, 164, 333, 197, 773, 165, 755, 202, 185];

    protected array $professionNames = [
        171 => 'Alquimia',
        164 => 'Herrería',
        333 => 'Encantamiento',
        197 => 'Sastrería',
        773 => 'Inscripción',
        165 => 'Peletería',
        755 => 'Joyería',
        202 => 'Ingeniería',
        185 => 'Cocina',
    ];

    public function handle(ProfessionApiService $professionService, RecipeWikiScraperService $scraper)
    {
        $total = count($this->professionIds);
        $completadas = 0;
        $errores = [];
        $startTime = now();

        $this->info("Iniciando sync de {$total} profesiones: " . implode(', ', $this->professionNames));
        $this->newLine();

        foreach ($this->professionIds as $index => $professionId) {
            $posicion = $index + 1;
            $nombre = $this->professionNames[$professionId] ?? "ID {$professionId}";
            $pendientes = $total - $posicion;
            $siguienteId = $this->professionIds[$index + 1] ?? null;
            $siguienteNombre = $siguienteId ? ($this->professionNames[$siguienteId] ?? "ID {$siguienteId}") : 'ninguna (última)';

            $this->info("═══════════════════════════════════════");
            $this->info("Procesando [{$posicion}/{$total}]: {$nombre} (blizzard_id: {$professionId})");
            $this->info("Completadas: {$completadas} | Pendientes después de esta: {$pendientes}");
            $this->info("Siguiente en cola: {$siguienteNombre}");
            $this->info("═══════════════════════════════════════");

            try {
                $syncResult = $professionService->syncRecipesForProfession($professionId, $this);

                $this->info("[{$nombre}] Sync completado: Procesadas {$syncResult['processed']} | Omitidas {$syncResult['skipped']}");

                if ($syncResult['processed'] === 0) {
                    $this->warn("[{$nombre}] Sin recetas nuevas, saltando scraping para esta profesión.");
                } else {
                    $this->scrapeReagentsForProfession($professionId, $nombre, $scraper);
                }

                $completadas++;
                $this->info("[{$nombre}] ✔ Profesión completada.");
            } catch (\Throwable $e) {
                $errores[] = "{$nombre}: {$e->getMessage()}";
                $this->error("[{$nombre}] ✘ ERROR: {$e->getMessage()}");
            }

            $elapsed = $startTime->diffInMinutes(now());
            $this->info("Tiempo transcurrido: {$elapsed} min");
            $this->newLine();
        }

        $this->newLine();
        $this->info("=== RESUMEN FINAL ===");
        $this->info("Completadas: {$completadas}/{$total}");
        $this->info("Errores: " . count($errores));

        if (!empty($errores)) {
            $this->warn("Detalle de errores:");
            foreach ($errores as $e) {
                $this->warn("  - {$e}");
            }
        }

        $totalElapsed = $startTime->diffInMinutes(now());
        $this->info("Tiempo total: {$totalElapsed} min");
    }

    protected function scrapeReagentsForProfession(int $blizzardProfessionId, string $nombre, RecipeWikiScraperService $scraper): void
    {
        $profession = Profession::where('blizzard_id', $blizzardProfessionId)->first();

        if (!$profession) {
            $this->warn("[{$nombre}] Profesión no encontrada en BD, saltando scraping.");
            return;
        }

        $recipes = Recipe::where('profession_id', $profession->id)
            ->whereNotNull('name_en')
            ->get();

        $this->info("[{$nombre}] Scraping reagents para {$recipes->count()} recetas...");
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
        $this->info("[{$nombre}] Scraping: Procesadas {$processed} | Fallidas {$failed} | Cantidades corregidas: {$quantitiesFixed}");
    }
}