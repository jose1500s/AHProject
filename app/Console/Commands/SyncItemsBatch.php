<?php

namespace App\Console\Commands;

use App\Http\Services\BlizzApiService;
use Illuminate\Console\Command;

class SyncItemsBatch extends Command
{
    protected $signature = 'items:sync {--limit=5000} {--realm=illidan}';

    public function handle(BlizzApiService $blizzard)
    {
        // Límite máximo de ejecución: 10 minutos
        set_time_limit(600);

        $startTime = microtime(true);
        $startDateTime = now();

        $this->info('========================================');
        $this->info('Inicio de sincronización');
        $this->info("Fecha/hora: {$startDateTime->format('Y-m-d H:i:s')}");
        $this->info("Realm: {$this->option('realm')}");
        $this->info("Límite: {$this->option('limit')}");
        $this->info('========================================');

        // Obtener auctions de Blizzard
        $auctions = $blizzard->getAuctions(
            $this->option('realm')
        );

        // Obtener IDs únicos de los items
        $uniqueItemIds = $blizzard->getUniqueItemIds($auctions);

        $totalAuctions = count($auctions);
        $totalUniqueItems = count($uniqueItemIds);

        $this->info("Auctions encontradas: {$totalAuctions}");
        $this->info("IDs de items únicos: {$totalUniqueItems}");
        $this->info('========================================');

        // Sincronizar solamente los items que todavía no existen
        $result = $blizzard->syncItemsBatch(
            $auctions,
            (int) $this->option('limit'),
            $this
        );

        // Tiempo final
        $endTime = microtime(true);
        $endDateTime = now();

        $executionTime = $endTime - $startTime;

        $minutes = floor($executionTime / 60);
        $seconds = round($executionTime % 60);

        $this->info('========================================');
        $this->info('Sincronización terminada');
        $this->info("Fecha/hora fin: {$endDateTime->format('Y-m-d H:i:s')}");
        $this->info("Duración: {$minutes} min {$seconds} seg");
        $this->info("Auctions encontradas: {$totalAuctions}");
        $this->info("IDs únicos: {$totalUniqueItems}");
        $this->info("Ya existentes en BD: {$result['existing']}");
        $this->info("Procesados: {$result['processed']}");
        $this->info("Restantes: {$result['remaining']}");
        $this->info('========================================');
    }
}