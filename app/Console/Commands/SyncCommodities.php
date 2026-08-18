<?php

namespace App\Console\Commands;

use App\Http\Services\BlizzApiService;
use Illuminate\Console\Command;
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
            return self::SUCCESS;
        }

        $this->info("Commodities sincronizadas: {$result['count']} auctions.");
        $this->newLine();

        $itemIds = DB::table('commodity_auctions')->distinct()->pluck('item_id')->all();
        $fakeAuctions = collect($itemIds)->map(fn($id) => ['item' => ['id' => $id]])->all();

        $this->info('Sincronizando catálogo de ítems nuevos...');
        $blizzard->syncItemsBatch($fakeAuctions, (int) $this->option('limit'), $this);

        return self::SUCCESS;
    }
}