<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\Profession;
use App\Models\Recipe;
use App\Models\RecipeReagent;
use Illuminate\Support\Facades\DB;

class ProfessionApiService
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

    public function getProfessionsIndex(): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->get("https://{$this->region}.api.blizzard.com/data/wow/profession/index", [
                'namespace' => "static-{$this->region}",
                'locale' => 'es_MX',
            ]);

        return $response->json('professions', []);
    }

    public function getProfessionDetail(int $professionId): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->get("https://{$this->region}.api.blizzard.com/data/wow/profession/{$professionId}", [
                'namespace' => "static-{$this->region}",
                'locale' => 'es_MX',
            ]);

        return $response->json();
    }

    public function getSkillTierRecipes(int $professionId, int $skillTierId): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->get("https://{$this->region}.api.blizzard.com/data/wow/profession/{$professionId}/skill-tier/{$skillTierId}", [
                'namespace' => "static-{$this->region}",
                'locale' => 'es_MX',
            ]);

        $categories = $response->json('categories', []);

        return collect($categories)
            ->flatMap(fn($cat) => $cat['recipes'] ?? [])
            ->map(fn($r) => $r['id'])
            ->unique()
            ->values()
            ->all();
    }

    public function getRecipeDetail(int $recipeId, string $locale = 'es_MX'): ?array
    {
        $response = Http::withToken($this->getAccessToken())
            ->get("https://{$this->region}.api.blizzard.com/data/wow/recipe/{$recipeId}", [
                'namespace' => "static-{$this->region}",
                'locale' => $locale,
            ]);

        if (!$response->ok()) {
            return null;
        }

        return $response->json();
    }

    public function getRecipeMedia(int $recipeId): ?string
    {
        $response = Http::withToken($this->getAccessToken())
            ->get("https://{$this->region}.api.blizzard.com/data/wow/media/recipe/{$recipeId}", [
                'namespace' => "static-{$this->region}",
                'locale' => 'es_MX',
            ]);

        if (!$response->ok()) {
            return null;
        }

        return collect($response->json('assets', []))->firstWhere('key', 'icon')['value'] ?? null;
    }

    public function resolveItemIdsByExactName(string $nameEn): array
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

    public function resolveItemIdByExactName(string $nameEn): ?int
    {
        $ids = $this->resolveItemIdsByExactName($nameEn);
        return $ids[count($ids) - 1] ?? null;
    }

    public function syncProfessions(): int
    {
        $professions = $this->getProfessionsIndex();

        $count = 0;

        foreach ($professions as $p) {
            Profession::updateOrCreate(
                ['blizzard_id' => $p['id']],
                ['name' => $p['name']]
            );
            $count++;
        }

        return $count;
    }

    public function getSkillTierIdsForProfession(int $professionId, string $expansionFilter = 'Midnight'): array
    {
        $detail = $this->getProfessionDetail($professionId);

        return collect($detail['skill_tiers'] ?? [])
            ->filter(fn($tier) => str_contains($tier['name'], $expansionFilter))
            ->pluck('id')
            ->all();
    }

    public function syncRecipesForProfession(
        int $professionBlizzardId,
        ?\Illuminate\Console\Command $command = null,
        string $expansionFilter = 'Midnight'
    ): array {
        $profession = Profession::where('blizzard_id', $professionBlizzardId)->first();

        if (!$profession) {
            if ($command) {
                $command->error("Profesión {$professionBlizzardId} no encontrada en BD, corre professions:sync primero");
            }
            return ['processed' => 0, 'skipped' => 0];
        }

        $skillTierIds = $this->getSkillTierIdsForProfession($professionBlizzardId, $expansionFilter);

        if ($command) {
            $command->info("Skill tiers encontrados: " . count($skillTierIds));
        }

        $recipeIds = collect($skillTierIds)
            ->flatMap(fn($tierId) => $this->getSkillTierRecipes($professionBlizzardId, $tierId))
            ->unique()
            ->values();

        if ($command) {
            $command->info("Recetas únicas encontradas: " . $recipeIds->count());
            $command->getOutput()->progressStart($recipeIds->count());
        }

        $processed = 0;
        $skipped = 0;
        $resolvedByName = 0;

        $recipeIds->chunk(20)->each(function ($chunk) use ($profession, $command, &$processed, &$skipped, &$resolvedByName) {
            foreach ($chunk as $recipeId) {
                $detail = $this->getRecipeDetail($recipeId, 'es_MX');

                if (!$detail || empty($detail['reagents'])) {
                    $skipped++;
                    if ($command) {
                        $command->getOutput()->progressAdvance();
                    }
                    continue;
                }

                $producesItemId = $detail['crafted_item']['id']
                    ?? $detail['alliance_crafted_item']['id']
                    ?? $detail['horde_crafted_item']['id']
                    ?? null;

                $detailEn = $this->getRecipeDetail($recipeId, 'en_US');
                $nameEn = $detailEn['name'] ?? null;

                $producesItemIdHigh = null;

                if ($producesItemId === null && !empty($detail['modified_crafting_slots']) && $nameEn) {
                    $ids = $this->resolveItemIdsByExactName($nameEn);
                    if (!empty($ids)) {
                        $producesItemId = $ids[0];
                        $producesItemIdHigh = count($ids) > 1 ? $ids[count($ids) - 1] : null;
                        $resolvedByName++;
                    }
                } elseif ($producesItemId !== null && $nameEn) {
                    $ids = $this->resolveItemIdsByExactName($nameEn);
                    if (count($ids) > 1) {
                        $producesItemIdHigh = collect($ids)->first(fn($id) => $id !== $producesItemId);
                    }
                }

                if ($producesItemId === null) {
                    $skipped++;
                    if ($command) {
                        $command->getOutput()->progressAdvance();
                    }
                    continue;
                }

                $iconUrl = $this->getRecipeMedia($recipeId);

                $recipe = Recipe::updateOrCreate(
                    ['blizzard_recipe_id' => $recipeId],
                    [
                        'profession_id' => $profession->id,
                        'name' => $detail['name'] ?? 'Desconocido',
                        'name_en' => $nameEn,
                        'produces_item_id' => $producesItemId,
                        'produces_item_id_high' => $producesItemIdHigh,
                        'produces_quantity' => $detail['crafted_quantity']['value'] ?? 1,
                        'rank' => $detail['rank'] ?? null,
                        'bonus_ids' => null,
                        'icon_url' => $iconUrl,
                    ]
                );

                RecipeReagent::where('recipe_id', $recipe->id)->delete();

                $reagentRows = collect($detail['reagents'])->map(fn($r) => [
                    'recipe_id' => $recipe->id,
                    'item_id' => $r['reagent']['id'],
                    'quantity' => $r['quantity'],
                    'is_optional' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                if (!empty($reagentRows)) {
                    RecipeReagent::insert($reagentRows);
                }

                $processed++;

                if ($command) {
                    $command->getOutput()->progressAdvance();
                }
            }
        });

        if ($command) {
            $command->getOutput()->progressFinish();
            $command->newLine();
            $command->info("Resueltas por nombre (sin crafted_item directo): {$resolvedByName}");
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }
}