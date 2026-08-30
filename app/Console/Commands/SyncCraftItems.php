<?php

namespace App\Console\Commands;

use App\Http\Services\BlizzApiService;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\RecipeReagent;
use Illuminate\Console\Command;

class SyncCraftItems extends Command
{
    protected $signature = 'crafts:sync-items';
    protected $description = 'Sincroniza catálogo (nombre + ícono) de todos los ítems usados en recetas de Best Crafts';

    public function handle(BlizzApiService $blizzApiService)
    {
        $producedIds = Recipe::whereNotNull('produces_item_id')->pluck('produces_item_id');
        $reagentIds = RecipeReagent::pluck('item_id');

        $allIds = $producedIds->merge($reagentIds)->unique()->values();

        $this->info("IDs únicos usados en Best Crafts: {$allIds->count()}");

        $existingIds = Item::whereIn('blizzard_id', $allIds)->pluck('blizzard_id');
        $missingIds = $allIds->diff($existingIds)->values();

        $this->info("Ya existen en BD: {$existingIds->count()}");
        $this->info("Faltan por sincronizar: {$missingIds->count()}");

        if ($missingIds->isEmpty()) {
            $this->info('Nada que hacer.');
            return;
        }

        $this->getOutput()->progressStart($missingIds->count());

        $missingIds->chunk(75)->each(function ($chunk) use ($blizzApiService) {
            $items = $blizzApiService->getItemsBulk($chunk->all());
            $mediaMap = $blizzApiService->getItemMediaBulk($chunk->all());

            foreach ($items as $item) {
                $iconUrl = $this->downloadIcon($blizzApiService, $item['blizzard_id'], $mediaMap[$item['blizzard_id']] ?? null);
                $item['icon_url'] = $iconUrl;

                Item::updateOrCreate(
                    ['blizzard_id' => $item['blizzard_id']],
                    $item
                );

                $this->getOutput()->progressAdvance();
            }
        });

        $this->getOutput()->progressFinish();
        $this->newLine();
        $this->info('Completado.');

        $stillMissing = $missingIds->diff(Item::whereIn('blizzard_id', $missingIds)->pluck('blizzard_id'));

        if ($stillMissing->isNotEmpty()) {
            $this->warn("IDs que Blizzard no reconoce (posible 404): " . $stillMissing->implode(', '));
        }
    }

    protected function downloadIcon(BlizzApiService $blizzApiService, int $itemId, ?string $mediaUrl): ?string
    {
        $method = new \ReflectionMethod($blizzApiService, 'downloadAndStoreIcon');
        return $method->invoke($blizzApiService, $itemId, $mediaUrl);
    }
}