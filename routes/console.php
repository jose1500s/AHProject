<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

function nowCdmxFormatted(): string
{
    return now('America/Mexico_City')->format('d/m/Y h:i:s A');
}

Schedule::call(function () {
    $now = now();

    $lastCheckTimestamp = Cache::get('commodities_last_check_timestamp');

    if ($lastCheckTimestamp !== null) {
        $elapsedSeconds = $now->timestamp - $lastCheckTimestamp;
        $elapsedMinutes = intdiv($elapsedSeconds, 60);

        if ($elapsedMinutes < 5) {
            $faltan = 5 - $elapsedMinutes;
            Log::info("[" . nowCdmxFormatted() . "] ⏳ Commodities: esperando, revisé hace {$elapsedMinutes} min (próximo intento en ~{$faltan} min)");
            return;
        }
    }

    Cache::put('commodities_last_check_timestamp', $now->timestamp, now()->addDay());

    Log::info("[" . nowCdmxFormatted() . "] 🔄 Commodities: preguntando a Blizzard si hay datos nuevos...");

    Artisan::call('commodities:sync');

    $hadChanges = Cache::get('commodities_last_sync_had_changes');
    $lastModified = Cache::get('commodities_last_modified');
    $lastModifiedFormatted = $lastModified
        ? \Illuminate\Support\Carbon::parse($lastModified)->timezone('America/Mexico_City')->format('d/m/Y h:i:s A')
        : 'desconocido';

    if ($hadChanges === true) {
        Log::info("[" . nowCdmxFormatted() . "] ✅ Commodities: Blizzard tenía datos nuevos (snapshot generado el {$lastModifiedFormatted}). Guardado en BD.");

        Log::info("[" . nowCdmxFormatted() . "] 📊 Recalculando recomendaciones, caídas y rebotes de mercado...");

        Artisan::call('commodities:analyze-market');
        $output = trim(Artisan::output());
        $summaryLine = collect(explode("\n", $output))->last(fn($line) => str_contains($line, 'Análisis completado')) ?? 'completado';

        Log::info("[" . nowCdmxFormatted() . "] ✅ Análisis de mercado actualizado.");
    } else {
        Log::info("[" . nowCdmxFormatted() . "] ⏸️ Commodities: Blizzard respondió sin cambios (su último snapshot sigue siendo el de {$lastModifiedFormatted}). No hace falta recalcular nada.");
    }
})
    ->everyMinute()
    ->name('commodities-sync')
    ->withoutOverlapping();