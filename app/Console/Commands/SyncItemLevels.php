<?php

namespace App\Console\Commands;

use App\Models\ItemLevelLookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class SyncItemLevels extends Command
{
    protected $signature = 'itemlevels:sync {--limit=200}';

    protected string $pythonPath;
    protected string $batchScriptPath;

    public function __construct()
    {
        parent::__construct();
        $this->pythonPath = base_path('tools/BonusIdTool/venv/Scripts/python.exe');
        $this->batchScriptPath = base_path('tools/BonusIdTool/batch_calc.py');
    }

    public function handle()
{
    ini_set('memory_limit', '1024M'); // <- agregar esto al inicio

    // trae solo item_id + bonus_signature ya calculada... pero calcularla necesita bonus_lists/modifiers,
    // así que primero deduplicamos a nivel SQL para no cargar 134k filas si muchas son repetidas
    $existingKeys = ItemLevelLookup::select('item_id', 'bonus_signature')
        ->get()
        ->map(fn ($r) => $r->item_id . '::' . $r->bonus_signature)
        ->flip();

    $combos = DB::table('auctions')
        ->select('item_id', DB::raw('bonus_lists::text as bonus_signature_raw'), DB::raw('modifiers::text as modifiers_raw'))
        ->whereNotNull('bonus_lists')
        ->whereRaw("bonus_lists::text != '[]'")
        ->distinct()
        ->get()
        ->map(function ($row) {
            $bonusIds = json_decode($row->bonus_signature_raw, true);
            sort($bonusIds);
            $modifiers = json_decode($row->modifiers_raw, true) ?? [];
            [$playerLevel, $contentTuningId] = \App\Http\Services\BlizzApiService::extractScalingModifiers($modifiers);

            $signature = implode(',', $bonusIds) . "|p{$playerLevel}|c{$contentTuningId}";

            return [
                'item_id' => $row->item_id,
                'bonus_ids' => $bonusIds,
                'player_level' => $playerLevel,
                'content_tuning_id' => $contentTuningId,
                'signature' => $signature,
            ];
        })
        ->reject(fn ($c) => empty($c['bonus_ids']))
        ->unique(fn ($c) => $c['item_id'] . '::' . $c['signature'])
        ->reject(fn ($c) => isset($existingKeys[$c['item_id'] . '::' . $c['signature']]))
        ->take((int) $this->option('limit'))
        ->values();

        $this->info("Combinaciones nuevas a procesar: {$combos->count()}");

        if ($combos->isEmpty()) {
            $this->info('Nada que hacer.');
            return;
        }

        $payload = $combos->map(fn($c) => [
            'item_id' => $c['item_id'],
            'bonus_ids' => $c['bonus_ids'],
            'player_level' => $c['player_level'],
            'content_tuning_id' => $c['content_tuning_id'],
        ])->values()->toJson();

        $this->info('Calculando en un solo proceso (puede tardar ~30s la primera vez, descargando las tablas)...');

        $result = Process::path(dirname($this->batchScriptPath))
            ->timeout(600)
            ->input($payload)
            ->run("\"{$this->pythonPath}\" \"{$this->batchScriptPath}\"");

        if (!$result->successful()) {
            $this->error('Error ejecutando el batch:');
            $this->error($result->errorOutput());
            return;
        }

        $results = json_decode($result->output(), true);

        if (!$results) {
            $this->error('No se pudo interpretar la salida del script.');
            $this->line($result->output());
            return;
        }

        $bar = $this->output->createProgressBar(count($results));

        foreach ($results as $r) {
            if (isset($r['error'])) {
                $bar->advance();
                continue;
            }

            $bonusIds = $r['bonus_ids'];
            sort($bonusIds);
            $playerLevel = $r['player_level'] ?? 0;
            $contentTuningId = $r['content_tuning_id'] ?? 0;

            $signature = implode(',', $bonusIds) . "|p{$playerLevel}|c{$contentTuningId}";

            $rawIlvl = $r['ilvl'];
            $seasonIlvl = config("season_ilvl_offsets.{$rawIlvl}", $rawIlvl);

            ItemLevelLookup::updateOrCreate(
                ['item_id' => $r['item_id'], 'bonus_signature' => $signature],
                ['raw_ilvl' => $rawIlvl, 'season_ilvl' => $seasonIlvl]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Terminado.');
    }
}