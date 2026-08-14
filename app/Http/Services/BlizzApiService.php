<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;


class BlizzApiService
{
    protected string $region;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->region = config('services.blizzard.region');
        $this->clientId = config('services.blizzard.client_id');
        $this->clientSecret = config('services.blizzard.client_secret');
    }

    protected function getAccessToken(): string
    {
        return Cache::remember('blizzard_access_token', now()->addHours(23), function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post('https://oauth.battle.net/token', [
                    'grant_type' => 'client_credentials',
                ]);

            return $response->json('access_token');
        });
    }

    public function getRealms(): array
    {
        return Cache::remember('blizzard_realms', now()->addDay(), function () {
            $response = Http::withToken($this->getAccessToken())
                ->get("https://{$this->region}.api.blizzard.com/data/wow/realm/index", [
                    'namespace' => "dynamic-{$this->region}",
                    'locale' => 'en_US',
                ]);

            return collect($response->json('realms', []))
                ->map(fn($r) => ['id' => $r['id'], 'name' => $r['name'], 'slug' => $r['slug']])
                ->values()->all();
        });
    }

    public function getConnectedRealmId(string $realmSlug): int
    {
        return Cache::remember("connected_realm_id:{$realmSlug}", now()->addWeek(), function () use ($realmSlug) {
            $response = Http::withToken($this->getAccessToken())
                ->get("https://{$this->region}.api.blizzard.com/data/wow/realm/{$realmSlug}", [
                    'namespace' => "dynamic-{$this->region}",
                    'locale' => 'en_US',
                ]);

            // el connected realm no viene como ID directo, viene embebido en una URL:
            // "https://us.api.blizzard.com/data/wow/connected-realm/11/..." -> extraemos el 11
            $href = $response->json('connected_realm.href');
            preg_match('/connected-realm\/(\d+)/', $href, $matches);

            return (int) $matches[1];
        });
    }

    public function getAuctions(string $realmSlug): array
    {
        $connectedRealmId = $this->getConnectedRealmId($realmSlug);
        $dataKey = "auctions:{$connectedRealmId}";
        $lastModifiedKey = "auctions_last_modified:{$connectedRealmId}";

        ini_set('memory_limit', '512M');

        $request = Http::withToken($this->getAccessToken())->timeout(60);

        if ($lastModified = Cache::get($lastModifiedKey)) {
            $request = $request->withHeaders(['If-Modified-Since' => $lastModified]);
        }

        $response = $request->get(
            "https://{$this->region}.api.blizzard.com/data/wow/connected-realm/{$connectedRealmId}/auctions",
            ['namespace' => "dynamic-{$this->region}", 'locale' => 'en_US']
        );

        if ($response->status() === 304) {
            return Cache::get($dataKey, []);
        }

        $data = $response->json('auctions', []);

        Cache::put($dataKey, $data, now()->addDay());
        Cache::put($lastModifiedKey, $response->header('Last-Modified'), now()->addDay());

        return $data;
    }

    public function getUniqueItemIds(array $auctions): array
    {
        return collect($auctions)
            ->pluck('item.id')
            ->unique()
            ->values()
            ->all();
    }
    public function getItemsBulk(array $itemIds): array
    {
        $token = $this->getAccessToken();

        $responses = Http::pool(
            fn($pool) =>
            collect($itemIds)->map(
                fn($id) =>
                $pool->as($id)
                    ->withToken($token)
                    ->timeout(15)
                    ->get("https://{$this->region}.api.blizzard.com/data/wow/item/{$id}", [
                        'namespace' => "static-{$this->region}",
                        'locale' => 'en_US',
                    ])
            )->all()
        );

        return collect($responses)
            ->filter(fn($r) => $r->ok())
            ->map(fn($r) => [
                'blizzard_id' => $r->json('id'),
                'name' => $r->json('name'),
                'quality' => strtolower($r->json('quality.type')),          // uncommon, rare, epic...
                'item_class' => $r->json('item_class.name'),                // "Armor"
                'item_subclass' => $r->json('item_subclass.name'),          // "Miscellaneous"
                'inventory_type' => $r->json('inventory_type.name'),        // "Shirt"
                'level' => $r->json('level'),
                'icon_url' => null, // lo llenamos aparte con el endpoint de media
            ])
            ->values()->all();
    }

    public function getItemMediaBulk(array $itemIds): array
    {
        $token = $this->getAccessToken();

        $responses = Http::pool(
            fn($pool) =>
            collect($itemIds)->map(
                fn($id) =>
                $pool->as($id)
                    ->withToken($token)
                    ->timeout(15)
                    ->get("https://{$this->region}.api.blizzard.com/data/wow/media/item/{$id}", [
                        'namespace' => "static-{$this->region}",
                        'locale' => 'en_US',
                    ])
            )->all()
        );

        return collect($responses)
            ->filter(fn($r) => $r->ok())
            ->mapWithKeys(fn($r, $id) => [
                $id => collect($r->json('assets', []))->firstWhere('key', 'icon')['value'] ?? null,
            ])
            ->all();
    }

    protected function downloadAndStoreIcon(int $itemId, ?string $iconUrl): ?string
    {
        if (!$iconUrl)
            return null;

        $path = "icons/{$itemId}.jpg";

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        try {
            $response = Http::timeout(15)->get($iconUrl);

            if ($response->ok()) {
                Storage::disk('public')->put($path, $response->body());
                return $path;
            }
        } catch (\Throwable $e) {
            // si falla (ej. el AccessDenied intermitente de Blizzard), se queda sin ícono por ahora
        }

        return null;
    }

    public function syncItemsBatch(
        array $auctions,
        int $limit = 5000,
        ?\Illuminate\Console\Command $command = null
    ): array {
        // Obtener todos los IDs únicos de las auctions
        $uniqueIds = $this->getUniqueItemIds($auctions);

        // Buscar cuáles de esos IDs ya existen en la bd
        $existingIds = Item::whereIn('blizzard_id', $uniqueIds)
            ->pluck('blizzard_id')
            ->all();

        // Eliminar de la lista todos los IDs que ya existen
        $missingIds = array_values(
            array_diff($uniqueIds, $existingIds)
        );

        // Aplicar el límite del batch
        $batch = array_slice($missingIds, 0, $limit);

        // Mostrar información en consola
        if ($command) {
            $command->info(
                "IDs únicos encontrados: " . count($uniqueIds)
            );

            $command->info(
                "IDs ya existentes en BD: " . count($existingIds)
            );

            $command->info(
                "IDs nuevos por procesar: " . count($missingIds)
            );

            $command->info(
                "IDs que se procesarán en este batch: " . count($batch)
            );

            $command->info(
                "IDs que quedarán pendientes: " .
                max(0, count($missingIds) - count($batch))
            );

            $command->newLine();
        }

        // No hay nada nuevo que procesar
        if (empty($batch)) {
            return [
                'processed' => 0,
                'remaining' => 0,
                'existing' => count($existingIds),
                'total_unique' => count($uniqueIds),
            ];
        }

        // Iniciar barra de progreso
        if ($command) {
            $command->getOutput()->progressStart(count($batch));
        }

        // Procesar en grupos de 75
        collect($batch)
            ->chunk(75)
            ->each(function ($chunk) use ($command) {

                // Obtener información de los items desde Blizzard
                $items = $this->getItemsBulk(
                    $chunk->all()
                );

                // Obtener información de los iconos
                $mediaMap = $this->getItemMediaBulk(
                    $chunk->all()
                );

                // Guardar items en PostgreSQL
                foreach ($items as $item) {

                    $item['icon_url'] = $this->downloadAndStoreIcon(
                        $item['blizzard_id'],
                        $mediaMap[$item['blizzard_id']] ?? null
                    );

                    Item::updateOrCreate(
                        [
                            'blizzard_id' => $item['blizzard_id']
                        ],
                        $item
                    );

                    // Actualizar barra de progreso
                    if ($command) {
                        $command->getOutput()->progressAdvance();
                    }
                }
            });

        // Terminar barra de progreso
        if ($command) {
            $command->getOutput()->progressFinish();
            $command->newLine();
        }

        return [
            'processed' => count($batch),
            'remaining' => count($missingIds) - count($batch),
            'existing' => count($existingIds),
            'total_unique' => count($uniqueIds),
        ];
    }
}