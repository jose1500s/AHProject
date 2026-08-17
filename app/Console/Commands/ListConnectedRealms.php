<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ListConnectedRealms extends Command
{
    /**
     * php artisan realms:list
     * php artisan realms:list --region=eu
     * php artisan realms:list --search=area-52
     */
    protected $signature = 'realms:list
                            {--region=us : Región de Blizzard (us, eu, kr, tw)}
                            {--search= : Filtra por nombre de realm (ej: area-52)}';

    protected $description = 'Muestra todos los connected realms de WoW agrupados, tal como los ve la Blizzard API ahora mismo';

    public function handle(): int
    {
        $region = $this->option('region');
        $search = $this->option('search');

        $this->info("Obteniendo token de acceso ({$region})...");
        $token = $this->getAccessToken($region);

        if (! $token) {
            $this->error('No se pudo obtener el access token. Revisa BLIZZARD_CLIENT_ID / BLIZZARD_CLIENT_SECRET en tu .env');
            return self::FAILURE;
        }

        $this->info('Consultando índice de connected realms...');
        $index = Http::withToken($token)
            ->get("https://{$region}.api.blizzard.com/data/wow/connected-realm/index", [
                'namespace' => "dynamic-{$region}",
                'locale' => 'en_US',
            ]);

        if ($index->failed()) {
            $this->error('Fallo al consultar el índice: ' . $index->status());
            return self::FAILURE;
        }

        $connectedRealms = $index->json('connected_realms', []);
        $total = count($connectedRealms);
        $this->info("Encontrados {$total} connected realm groups. Expandiendo cada uno...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $rows = [];

        foreach ($connectedRealms as $entry) {
            // La URL trae el ID embebido, ej: .../connected-realm/1136?namespace=...
            preg_match('/connected-realm\/(\d+)/', $entry['href'], $matches);
            $connectedRealmId = $matches[1] ?? null;

            if (! $connectedRealmId) {
                $bar->advance();
                continue;
            }

            $detail = Http::withToken($token)
                ->get("https://{$region}.api.blizzard.com/data/wow/connected-realm/{$connectedRealmId}", [
                    'namespace' => "dynamic-{$region}",
                    'locale' => 'en_US',
                ]);

            if ($detail->successful()) {
                $realms = $detail->json('realms', []);

                $realmNames = collect($realms)
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all();

                // Tomamos la zona horaria del primer realm del grupo (los realms
                // conectados comparten reloj, así que todos deberían coincidir).
                $timezone = $realms[0]['timezone'] ?? null;
                [$localTime, $diffLabel] = $this->timeInfo($timezone);

                $rows[] = [
                    'connected_realm_id' => $connectedRealmId,
                    'realms' => implode(', ', $realmNames),
                    'count' => count($realmNames),
                    'timezone' => $timezone ?? 'N/D',
                    'local_time' => $localTime,
                    'diff_cdmx' => $diffLabel,
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Filtrado opcional por nombre de realm
        if ($search) {
            $rows = array_filter($rows, function ($row) use ($search) {
                return stripos($row['realms'], $search) !== false;
            });
        }

        // Ordenar por cantidad de realms conectados (los grupos más grandes primero)
        usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);

        $this->table(
            ['connected_realm_id', 'Realms agrupados', '# realms', 'Zona horaria', 'Hora actual', 'Dif. vs CDMX'],
            array_map(fn ($r) => [
                $r['connected_realm_id'],
                $r['realms'],
                $r['count'],
                $r['timezone'],
                $r['local_time'],
                $r['diff_cdmx'],
            ], $rows)
        );

        $this->info(count($rows) . ' grupo(s) mostrado(s) de ' . $total . ' totales.');

        return self::SUCCESS;
    }

    /**
     * Devuelve [hora_local_formateada, diferencia_vs_cdmx_legible] para una
     * zona horaria dada, comparada contra America/Mexico_City en este instante.
     *
     * @return array{0: string, 1: string}
     */
    private function timeInfo(?string $timezone): array
    {
        if (! $timezone) {
            return ['N/D', 'N/D'];
        }

        try {
            $cdmx = Carbon::now('America/Mexico_City');
            $realmTime = Carbon::now($timezone);

            $localTime = $realmTime->format('H:i') . ' (' . $realmTime->format('D') . ')';

            // Diferencia en horas entre el offset del realm y el de CDMX
            $diffMinutes = ($realmTime->utcOffset() - $cdmx->utcOffset());
            $diffHours = $diffMinutes / 60;

            if ($diffHours === 0.0) {
                $diffLabel = 'Misma hora que CDMX';
            } else {
                $sign = $diffHours > 0 ? '+' : '';
                // Soporta offsets de media hora (poco común en US, pero por si acaso)
                $formatted = floor($diffHours) == $diffHours
                    ? (string) (int) $diffHours
                    : number_format($diffHours, 1);

                $diffLabel = "{$sign}{$formatted}h vs CDMX";
            }

            return [$localTime, $diffLabel];
        } catch (\Exception $e) {
            return ['Zona inválida', 'N/D'];
        }
    }

    private function getAccessToken(string $region): ?string
    {
        return Cache::remember("blizzard_token_{$region}", 3500, function () use ($region) {
            $tokenRegion = $region === 'cn' ? 'www.battlenet.com.cn' : "{$region}.battle.net";

            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.blizzard.client_id'),
                    config('services.blizzard.client_secret')
                )
                ->post("https://{$tokenRegion}/oauth/token", [
                    'grant_type' => 'client_credentials',
                ]);

            return $response->successful() ? $response->json('access_token') : null;
        });
    }
}
