<?php

namespace App\Console\Commands;

use App\Http\Services\ProfessionApiService;
use Illuminate\Console\Command;

class SyncProfessions extends Command
{
    protected $signature = 'professions:sync';
    protected $description = 'Sincroniza el catálogo de profesiones desde Blizzard';

    public function handle(ProfessionApiService $service)
    {
        $this->info('Sincronizando profesiones...');

        $count = $service->syncProfessions();

        $this->info("Profesiones sincronizadas: {$count}");
    }
}