<?php

namespace App\Console\Commands;

use App\Models\ItemLevelLookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class SyncItemLevels extends Command
{
    protected $signature = 'itemlevels:sync {--limit=200}';

    protected $description = 'Sincroniza los item levels de las combinaciones de bonus encontradas en las auctions';

    protected string $pythonPath;
    protected string $batchScriptPath;

    public function __construct()
    {
        parent::__construct();

        $this->pythonPath = base_path(
            'tools/BonusIdTool/venv/Scripts/python.exe'
        );

        $this->batchScriptPath = base_path(
            'tools/BonusIdTool/batch_calc.py'
        );
    }

    public function handle()
    {
        ini_set('memory_limit', '1024M');

        $limit = max(1, (int) $this->option('limit'));

        $this->newLine();

        $this->line('==========================================');
        $this->line('      SINCRONIZACIÓN DE ITEM LEVELS');
        $this->line('==========================================');
        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | 1. Cargar combinaciones existentes
        |--------------------------------------------------------------------------
        */

        $this->info('[1/5] Cargando combinaciones existentes...');

        $existingKeys = ItemLevelLookup::select(
            'item_id',
            'bonus_signature'
        )
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    $row->item_id . '::' . $row->bonus_signature => true,
                ];
            })
            ->all();

        $existingCount = count($existingKeys);

        $this->line(
            '      ✓ ' .
            number_format($existingCount) .
            ' combinaciones existentes'
        );

        /*
        |--------------------------------------------------------------------------
        | 2. Contar auctions
        |--------------------------------------------------------------------------
        */

        $this->newLine();
        $this->info('[2/5] Analizando auctions...');

        $auctionCount = DB::table('auctions')->count();

        $this->line(
            '      ✓ ' .
            number_format($auctionCount) .
            ' auctions encontradas'
        );

        /*
        |--------------------------------------------------------------------------
        | 3. Buscar TODAS las combinaciones únicas pendientes
        |--------------------------------------------------------------------------
        |
        | Aquí recorremos todas las auctions porque necesitamos saber:
        |
        | - cuántas combinaciones faltan en total
        | - cuáles son las primeras $limit que vamos a procesar
        |
        | No almacenamos los datos completos de todas las combinaciones.
        | Solamente guardamos una key para evitar duplicados.
        |
        */

        $this->newLine();
        $this->info('[3/5] Analizando combinaciones únicas...');

        $combos = collect();

        /*
        |--------------------------------------------------------------------------
        | Keys que ya conocemos
        |--------------------------------------------------------------------------
        */

        $seenKeys = $existingKeys;

        /*
        |--------------------------------------------------------------------------
        | Total de combinaciones nuevas encontradas
        |--------------------------------------------------------------------------
        */

        $missingTotal = 0;

        /*
        |--------------------------------------------------------------------------
        | Cuántas nuevas vamos a procesar en este run
        |--------------------------------------------------------------------------
        */

        $processedThisRun = 0;

        $chunkSize = 5000;

        $progressBar = $this->output->createProgressBar(
            $auctionCount
        );

        $progressBar->setFormat(
            '      %current%/%max% [%bar%] %percent:3s%% | nuevas: %message%'
        );

        $progressBar->setMessage('0');

        $progressBar->start();

        DB::table('auctions')
            ->select(
                'id',
                'item_id',
                'bonus_lists',
                'modifiers'
            )
            ->whereNotNull('bonus_lists')
            ->whereRaw("bonus_lists::text != '[]'")
            ->orderBy('id')
            ->chunkById(
                $chunkSize,
                function ($rows) use (
                    &$combos,
                    &$seenKeys,
                    &$missingTotal,
                    &$processedThisRun,
                    $limit,
                    $progressBar
                ) {
                    foreach ($rows as $row) {
                        $bonusIds = json_decode(
                            $row->bonus_lists,
                            true
                        );

                        if (!is_array($bonusIds) || empty($bonusIds)) {
                            continue;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Ordenar bonus IDs
                        |--------------------------------------------------------------------------
                        */

                        sort($bonusIds);

                        /*
                        |--------------------------------------------------------------------------
                        | Modifiers
                        |--------------------------------------------------------------------------
                        */

                        $modifiers = json_decode(
                            $row->modifiers ?? '[]',
                            true
                        ) ?? [];

                        /*
                        |--------------------------------------------------------------------------
                        | Scaling
                        |--------------------------------------------------------------------------
                        */

                        [
                            $playerLevel,
                            $contentTuningId
                        ] = \App\Http\Services\BlizzApiService::extractScalingModifiers(
                            $modifiers
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | Signature
                        |--------------------------------------------------------------------------
                        */

                        $signature =
                            implode(',', $bonusIds)
                            . "|p{$playerLevel}"
                            . "|c{$contentTuningId}";

                        $key =
                            $row->item_id .
                            '::' .
                            $signature;

                        /*
                        |--------------------------------------------------------------------------
                        | Ya existe / ya fue encontrado
                        |--------------------------------------------------------------------------
                        */

                        if (isset($seenKeys[$key])) {
                            continue;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Es una combinación nueva
                        |--------------------------------------------------------------------------
                        */

                        $seenKeys[$key] = true;

                        $missingTotal++;

                        /*
                        |--------------------------------------------------------------------------
                        | Solamente guardamos los primeros $limit
                        |--------------------------------------------------------------------------
                        */

                        if ($processedThisRun < $limit) {
                            $combos->push([
                                'item_id' => $row->item_id,
                                'bonus_ids' => $bonusIds,
                                'player_level' => $playerLevel,
                                'content_tuning_id' => $contentTuningId,
                                'signature' => $signature,
                            ]);

                            $processedThisRun++;
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Actualizar progreso
                    |--------------------------------------------------------------------------
                    */

                    $progressBar->advance($rows->count());

                    $progressBar->setMessage(
                        number_format($missingTotal)
                    );
                },
                'id'
            );

        $progressBar->finish();

        $this->newLine(2);

        /*
        |--------------------------------------------------------------------------
        | Resumen de combinaciones
        |--------------------------------------------------------------------------
        */

        $totalCombinations = $existingCount + $missingTotal;

        $this->line(
            '      ✓ ' .
            number_format($missingTotal) .
            ' combinaciones pendientes encontradas'
        );

        $this->line(
            '      ✓ ' .
            number_format($totalCombinations) .
            ' combinaciones totales detectadas'
        );

        $this->line(
            '      ✓ ' .
            number_format($processedThisRun) .
            ' serán procesadas en este run'
        );

        /*
        |--------------------------------------------------------------------------
        | No hay nada que procesar
        |--------------------------------------------------------------------------
        */

        if ($combos->isEmpty()) {
            $this->newLine();

            $this->line('==========================================');
            $this->line('              ESTADO ACTUAL');
            $this->line('==========================================');

            $this->line(
                '      Total combinaciones: ' .
                number_format($totalCombinations)
            );

            $this->line(
                '      Ya procesadas:       ' .
                number_format($existingCount)
            );

            $this->line(
                '      Pendientes:           0'
            );

            $this->line(
                '      Progreso:             100%'
            );

            $this->line('==========================================');

            $this->newLine();

            $this->info('✓ No hay combinaciones nuevas que procesar.');

            return self::SUCCESS;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Ejecutar Python
        |--------------------------------------------------------------------------
        */

        $this->newLine();
        $this->info('[4/5] Calculando item levels con BonusIdTool...');

        $payload = $combos
            ->map(function ($combo) {
                return [
                    'item_id' => $combo['item_id'],
                    'bonus_ids' => $combo['bonus_ids'],
                    'player_level' => $combo['player_level'],
                    'content_tuning_id' => $combo['content_tuning_id'],
                ];
            })
            ->values()
            ->toJson();

        $this->line(
            '      Calculando ' .
            number_format($combos->count()) .
            ' combinaciones...'
        );

        $result = Process::path(
            dirname($this->batchScriptPath)
        )
            ->timeout(600)
            ->input($payload)
            ->run(
                "\"{$this->pythonPath}\" \"{$this->batchScriptPath}\""
            );

        if (!$result->successful()) {
            $this->newLine();

            $this->error(
                'Error ejecutando BonusIdTool:'
            );

            $this->error(
                $result->errorOutput()
            );

            return self::FAILURE;
        }

        $results = json_decode(
            $result->output(),
            true
        );

        if (!is_array($results)) {
            $this->error(
                'No se pudo interpretar la salida del script Python.'
            );

            $this->line(
                $result->output()
            );

            return self::FAILURE;
        }

        $this->line(
            '      ✓ ' .
            number_format(count($results)) .
            ' resultados calculados'
        );

        /*
        |--------------------------------------------------------------------------
        | 5. Guardar resultados
        |--------------------------------------------------------------------------
        */

        $this->newLine();
        $this->info('[5/5] Guardando resultados...');

        $saveProgress = $this->output->createProgressBar(
            count($results)
        );

        $saveProgress->setFormat(
            '      %current%/%max% [%bar%] %percent:3s%%'
        );

        $saveProgress->start();

        $saved = 0;
        $errors = 0;

        foreach ($results as $r) {
            /*
            |--------------------------------------------------------------------------
            | Resultado con error
            |--------------------------------------------------------------------------
            */

            if (isset($r['error'])) {
                $errors++;

                $saveProgress->advance();

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Bonus IDs
            |--------------------------------------------------------------------------
            */

            $bonusIds = $r['bonus_ids'] ?? [];

            sort($bonusIds);

            /*
            |--------------------------------------------------------------------------
            | Scaling
            |--------------------------------------------------------------------------
            */

            $playerLevel = $r['player_level'] ?? 0;

            $contentTuningId = $r['content_tuning_id'] ?? 0;

            /*
            |--------------------------------------------------------------------------
            | Signature
            |--------------------------------------------------------------------------
            */

            $signature =
                implode(',', $bonusIds)
                . "|p{$playerLevel}"
                . "|c{$contentTuningId}";

            /*
            |--------------------------------------------------------------------------
            | Item level
            |--------------------------------------------------------------------------
            */

            $rawIlvl = $r['ilvl'];

            $seasonIlvl = config(
                "season_ilvl_offsets.{$rawIlvl}",
                $rawIlvl
            );

            /*
            |--------------------------------------------------------------------------
            | Guardar
            |--------------------------------------------------------------------------
            */

            ItemLevelLookup::updateOrCreate(
                [
                    'item_id' => $r['item_id'],
                    'bonus_signature' => $signature,
                ],
                [
                    'raw_ilvl' => $rawIlvl,
                    'season_ilvl' => $seasonIlvl,
                ]
            );

            $saved++;

            $saveProgress->advance();
        }

        $saveProgress->finish();

        $this->newLine(2);

        /*
        |--------------------------------------------------------------------------
        | Estado final
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | Las que no pudieron guardarse por error siguen pendientes.
        |--------------------------------------------------------------------------
        */

        $pendingAfterRun = max(
            0,
            $missingTotal - $saved
        );

        /*
        |--------------------------------------------------------------------------
        | Total acumulado real
        |--------------------------------------------------------------------------
        */

        $totalProcessedAfterRun =
            $existingCount + $saved;

        /*
        |--------------------------------------------------------------------------
        | Porcentaje
        |--------------------------------------------------------------------------
        */

        $completionPercentage = $totalCombinations > 0
            ? ($totalProcessedAfterRun / $totalCombinations) * 100
            : 100;

        /*
        |--------------------------------------------------------------------------
        | Resultado
        |--------------------------------------------------------------------------
        */

        $this->line('==========================================');
        $this->line('              RESULTADO');
        $this->line('==========================================');

        $this->line(
            '      Total combinaciones:       ' .
            number_format($totalCombinations)
        );

        $this->line(
            '      Ya existentes antes:       ' .
            number_format($existingCount)
        );

        $this->line(
            '      Procesadas este run:       ' .
            number_format($combos->count())
        );

        $this->line(
            '      Guardadas correctamente:   ' .
            number_format($saved)
        );

        $this->line(
            '      Con error:                 ' .
            number_format($errors)
        );

        $this->line(
            '      Total procesadas:          ' .
            number_format($totalProcessedAfterRun)
        );

        $this->line(
            '      Total pendientes ahora:    ' .
            number_format($pendingAfterRun)
        );

        $this->line(
            '      Progreso total:            ' .
            number_format($completionPercentage, 2) .
            '%'
        );

        $this->line('==========================================');

        $this->newLine();

        if ($pendingAfterRun > 0) {
            $this->info(
                'Quedan ' .
                number_format($pendingAfterRun) .
                ' combinaciones pendientes.'
            );
        } else {
            $this->info(
                '✓ Todas las combinaciones han sido procesadas.'
            );
        }

        return self::SUCCESS;
    }
}