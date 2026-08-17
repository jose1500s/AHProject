<?php
// app/Console/Commands/SyncItemsBatch.php
namespace App\Console\Commands;

use App\Http\Services\BlizzApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncItemsBatch extends Command
{
    protected $signature = 'items:sync {--limit=5000} {--realm=illidan}';

    public function handle(BlizzApiService $blizzard)
    {
        set_time_limit(600);

        $startTime = microtime(true);
        $startDateTime = now();

        $this->info('========================================');
        $this->info('Inicio de sincronización');
        $this->info("Fecha/hora: {$startDateTime->format('Y-m-d H:i:s')}");
        $this->info("Realm: {$this->option('realm')}");
        $this->info("Límite: {$this->option('limit')}");
        $this->info('========================================');

        // asegura que la tabla auctions esté al día antes de sacar los IDs
        $connectedRealmId = $blizzard->getConnectedRealmId($this->option('realm'));
        $blizzard->syncAuctionsToDb($this->option('realm'));

        // saca los IDs únicos directo de la BD, no del cache viejo
        $uniqueItemIds = DB::table('auctions')
            ->where('connected_realm_id', $connectedRealmId)
            ->distinct()
            ->pluck('item_id')
            ->all();

        $totalAuctions = DB::table('auctions')->where('connected_realm_id', $connectedRealmId)->count();
        $totalUniqueItems = count($uniqueItemIds);

        $this->info("Auctions encontradas: {$totalAuctions}");
        $this->info("IDs de items únicos: {$totalUniqueItems}");
        $this->info('========================================');

        $result = $blizzard->syncItemsBatch(
            array_map(fn ($id) => ['item' => ['id' => $id]], $uniqueItemIds), // formato compatible con getUniqueItemIds interno
            (int) $this->option('limit'),
            $this
        );

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