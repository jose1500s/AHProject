<?php

namespace App\Console\Commands;

use App\Http\Services\BlizzApiService;
use Illuminate\Console\Command;

class RetryMissingIcons extends Command
{
    protected $signature = 'icons:retry';

    public function handle(BlizzApiService $blizzard)
    {
        set_time_limit(600);

        $this->info('========================================');
        $this->info('Reintentando íconos faltantes');
        $this->info('========================================');

        $result = $blizzard->retryMissingIcons($this);

        $this->info('========================================');
        $this->info("Procesados: {$result['processed']}");
        $this->info("Corregidos: {$result['fixed']}");
        $this->info("Siguen sin ícono: {$result['still_missing']}");
        $this->info('========================================');
    }
}