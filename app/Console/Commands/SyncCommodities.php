<?php

namespace App\Console\Commands;

use App\Http\Services\BlizzApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncCommodities extends Command
{
    protected $signature = 'commodities:sync {--force} {--limit=5000}';

    protected $description = 'Sincroniza las auctions de commodities y su catálogo de ítems';

    public function handle(BlizzApiService $blizzard)
    {
        $this->info('Sincronizando commodities...');
        $result = $blizzard->syncCommoditiesToDb((bool) $this->option('force'));

        if (!$result['updated']) {
            $this->info('Sin cambios (304) o sync concurrente en progreso.');
            // decida si vale la pena reintentar en 20 min
            Cache::put('commodities_last_sync_had_changes', false, now()->addHours(2));
            return self::SUCCESS;
        }

        $this->info("Commodities sincronizadas: {$result['count']} auctions.");
        $this->newLine();

        $itemIds = DB::table('commodity_auctions')->distinct()->pluck('item_id')->all();
        $fakeAuctions = collect($itemIds)->map(fn($id) => ['item' => ['id' => $id]])->all();

        $this->info('Sincronizando catálogo de ítems nuevos...');
        $blizzard->syncItemsBatch($fakeAuctions, (int) $this->option('limit'), $this);

        Cache::put('commodities_last_sync_had_changes', true, now()->addHours(2));

        return self::SUCCESS;
    }
}