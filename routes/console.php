<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $nowCdmx = now('America/Mexico_City');
    $now = now();

    $lastCheckTimestamp = Cache::get('commodities_last_check_timestamp');
    $hadChanges = Cache::get('commodities_last_sync_had_changes');

    if ($lastCheckTimestamp) {
        $elapsedMinutes = intdiv($now->timestamp - $lastCheckTimestamp, 60);

        if ($hadChanges === true && $elapsedMinutes < 55) {
            Log::info('[commodities-sync] skip: ya sincronizado recientemente', [
                'hora_cdmx' => $nowCdmx->format('Y-m-d H:i:s'),
                'elapsedMinutes' => $elapsedMinutes,
            ]);
            return;
        }

        if ($hadChanges === false && $elapsedMinutes < 5) {
            Log::info('[commodities-sync] skip: esperando ventana de reintento', [
                'hora_cdmx' => $nowCdmx->format('Y-m-d H:i:s'),
                'elapsedMinutes' => $elapsedMinutes,
            ]);
            return;
        }
    }

    Cache::put('commodities_last_check_timestamp', $now->timestamp, now()->addDay());

    Log::info('[commodities-sync] sincronizando ahora', [
        'hora_cdmx' => $nowCdmx->format('Y-m-d H:i:s'),
    ]);

    Artisan::call('commodities:sync');

    Log::info('[commodities-sync] resultado', [
        'hora_cdmx' => now('America/Mexico_City')->format('Y-m-d H:i:s'),
        'had_changes' => Cache::get('commodities_last_sync_had_changes'),
        'last_modified' => Cache::get('commodities_last_modified'),
    ]);
})
    ->everyMinute()
    ->name('commodities-sync')
    ->withoutOverlapping();