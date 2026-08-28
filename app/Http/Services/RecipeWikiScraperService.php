<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RecipeWikiScraperService
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

    public function buildWikiUrl(string $nameEn): string
    {
        $slug = str_replace(' ', '_', $nameEn);
        $slug = str_replace("'", '%27', $slug);

        return "https://warcraft.wiki.gg/wiki/{$slug}";
    }

    public function fetchWikiHtml(string $url): ?string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'AuctionTerminal/1.0 (personal WoW auction tool; contact via project repo)',
        ])->timeout(15)->get($url);

        if (!$response->ok()) {
            return null;
        }

        return $response->body();
    }

    public function parseReagents(string $html): array
    {
        $reagents = [];

        if (!preg_match('/Reagents:.*?<tbody>(.*?)<\/tbody>/is', $html, $mainMatch)) {
            return $reagents;
        }

        preg_match_all('/<td>\s*(\d+)x\s*<span class="nobreak">.*?<a href="\/wiki\/[^"]+" title="([^"]+)"/is', $mainMatch[1], $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $reagents[] = [
                'quantity' => (int) $m[1],
                'name_en' => trim(html_entity_decode($m[2])),
                'is_optional' => false,
            ];
        }

        if (preg_match('/Finishing reagents:.*?<tbody>(.*?)<\/tbody>\s*<\/table>/is', $html, $finishMatch)) {
            if (!str_contains($finishMatch[0], 'External links')) {
                preg_match_all('/<a href="\/wiki\/[^"]+" title="([^"]+)"/is', $finishMatch[1], $finMatches, PREG_SET_ORDER);

                foreach ($finMatches as $m) {
                    $name = trim(html_entity_decode($m[1]));

                    if (str_contains($name, 'Finishing crafting reagent')) {
                        continue;
                    }

                    $reagents[] = [
                        'quantity' => 1,
                        'name_en' => $name,
                        'is_optional' => true,
                    ];
                }
            }
        }

        return $reagents;
    }

    public function parseCraftedQuantity(string $html): int
    {
        if (preg_match('/(\d+)x are created at a time/i', $html, $m)) {
            return (int) $m[1];
        }

        return 1;
    }

    public function resolveItemIdsByName(string $nameEn): array
    {
        return Cache::remember("item_ids_by_name_en:{$nameEn}", now()->addWeek(), function () use ($nameEn) {
            $response = Http::withToken($this->getAccessToken())
                ->get("https://{$this->region}.api.blizzard.com/data/wow/search/item", [
                    'namespace' => "static-{$this->region}",
                    'locale' => 'en_US',
                    'name.en_US' => $nameEn,
                    '_pageSize' => 50,
                ]);

            $results = $response->json('results', []);

            $exactMatches = collect($results)->filter(function ($r) use ($nameEn) {
                $itemName = $r['data']['name']['en_US'] ?? null;
                return $itemName !== null && strcasecmp($itemName, $nameEn) === 0;
            });

            return $exactMatches->pluck('data.id')->sort()->values()->all();
        });
    }

    public function resolveItemIdByName(string $nameEn): ?int
    {
        $ids = $this->resolveItemIdsByName($nameEn);
        return !empty($ids) ? $ids[count($ids) - 1] : null;
    }
}