<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
}