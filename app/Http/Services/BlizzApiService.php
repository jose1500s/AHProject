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
                ->map(fn($r) => ['id' => $r['id'], 'name' => $r['name']])
                ->values()->all();
        });
    }
}