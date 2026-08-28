<?php

namespace App\Console\Commands;

use App\Http\Services\ProfessionApiService;
use Illuminate\Console\Command;

class SyncRecipes extends Command
{
    protected $signature = 'recipes:sync {profession_id : Blizzard profession ID}';
    protected $description = 'Sincroniza recetas de una profesión específica desde Blizzard';

    public function handle(ProfessionApiService $service)
    {
        $professionId = (int) $this->argument('profession_id');

        $this->info("Sincronizando recetas de la profesión {$professionId}...");

        $result = $service->syncRecipesForProfession($professionId, $this);

        $this->info("Procesadas: {$result['processed']} | Sin reagents/omitidas: {$result['skipped']}");
    }
}