<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// sync principal de commodities: cada hora + 5 minutos de margen
Schedule::command('commodities:sync')
    ->cron('5 * * * *')
    ->name('commodities-sync-main')
    ->withoutOverlapping();

// reintento adaptativo: si el sync principal (o el reintento anterior) no trajo cambios, se vuelve a intentar 20 min después. Se checa cada minuto pero solo actúa si el intento anterior fue "sin cambios" y ya pasaron esos 20 min.
Schedule::call(function () {
    $hadChanges = Cache::get('commodities_last_sync_had_changes');

    if ($hadChanges !== false) {
        return;
    }

    $lastAttempt = Cache::get('commodities_retry_attempted_at');
    $now = now();

    if ($lastAttempt && $now->diffInMinutes($lastAttempt) < 20) {
        return;
    }

    if ($lastAttempt) {
        Cache::forget('commodities_last_sync_had_changes');
        Cache::forget('commodities_retry_attempted_at');
        return;
    }

    Cache::put('commodities_retry_attempted_at', $now, now()->addHours(2));
    Artisan::call('commodities:sync');
})
    ->everyMinute()
    ->name('commodities-sync-retry')
    ->withoutOverlapping();