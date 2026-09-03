<?php

namespace App\Http\Services;

use App\Models\CommodityPriceHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CommodityMarketAnalysisService
{
    private const WINDOW_DAYS = 7;
    private const MIN_HISTORY_DAYS = 14;
    private const MIN_AVG_VOLUME = 150;
    private const MIN_LISTING_CONSISTENCY = 0.7;
    private const MIN_MOVE_PERCENT = 10;
    private const MIN_CURRENT_PRICE_COPPER = 200000;
    private const AH_COMMISSION = 0.05;
    private const RECOMMENDATIONS_CACHE_KEY = 'commodity_recommendations';
    private const REBOUNDS_CACHE_KEY = 'commodity_price_rebounds';
    private const BREAKEVEN_DROPS_CACHE_KEY = 'commodity_breakeven_drops';
    private const REBOUND_LOOKBACK_DAYS = 4;
    private const MIN_REBOUND_PERCENT = 8;

    public function breakevenPercent(): float
    {
        return round(((1 / (1 - self::AH_COMMISSION)) - 1) * 100, 2);
    }

    public function getStats(int $itemId): ?array
    {
        $windowStart = now()->subDays(self::WINDOW_DAYS);

        $rows = CommodityPriceHistory::where('item_id', $itemId)
            ->where('snapshot_at', '>=', $windowStart)
            ->orderBy('snapshot_at')
            ->get(['min_price_copper', 'listings', 'volume', 'snapshot_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $current = (int) $rows->last()->min_price_copper;
        $prices = $rows->pluck('min_price_copper')->map(fn($p) => (int) $p)->sort()->values();

        $median = $this->percentile($prices, 50);
        $projectionMin = $this->percentile($prices, 5);
        $projectionMax = $this->percentile($prices, 95);
        $volatility = $this->stdDev($prices);
        $avgVolume = (float) $rows->avg('listings');

        $totalSnapshots = $rows->count();
        $snapshotsWithListings = $rows->where('listings', '>', 0)->count();
        $listingConsistency = $totalSnapshots > 0 ? $snapshotsWithListings / $totalSnapshots : 0;

        $price24hAgo = $this->nearestPrice($rows, now()->subHours(24));
        $percentChangeVsYesterday = $price24hAgo
            ? round((($current - $price24hAgo) / $price24hAgo) * 100, 1)
            : 0.0;

        $trend = 'estable';
        if ($percentChangeVsYesterday <= -2) {
            $trend = 'bajando';
        } elseif ($percentChangeVsYesterday >= 2) {
            $trend = 'subiendo';
        }

        $earliestEver = CommodityPriceHistory::where('item_id', $itemId)->min('snapshot_at');
        $historyDays = $earliestEver ? Carbon::parse($earliestEver)->diffInDays(now()) : 0;

        $discountPercent = $median > 0
            ? round((($median - $current) / $median) * 100, 1)
            : 0.0;

        $recentWindowStart = now()->subDays(self::REBOUND_LOOKBACK_DAYS);
        $recentRows = $rows->where('snapshot_at', '>=', $recentWindowStart);
        $recentMin = $recentRows->isNotEmpty() ? (int) $recentRows->min('min_price_copper') : $current;
        $reboundPercent = $recentMin > 0
            ? round((($current - $recentMin) / $recentMin) * 100, 1)
            : 0.0;

        $previousRow = $rows->count() >= 2 ? $rows->slice(-2, 1)->first() : null;
        $previousPrice = $previousRow ? (int) $previousRow->min_price_copper : null;
        $dropSincePreviousPercent = ($previousPrice && $previousPrice > 0)
            ? round((($previousPrice - $current) / $previousPrice) * 100, 2)
            : 0.0;

        return [
            'item_id' => $itemId,
            'current_price_copper' => $current,
            'median_price_copper' => $median,
            'projection_min_copper' => $projectionMin,
            'projection_max_copper' => $projectionMax,
            'volatility_copper' => (int) round($volatility),
            'avg_volume' => round($avgVolume, 1),
            'listing_consistency' => round($listingConsistency, 2),
            'trend' => $trend,
            'percent_change_vs_yesterday' => $percentChangeVsYesterday,
            'discount_percent' => max(0, $discountPercent),
            'recent_min_copper' => $recentMin,
            'rebound_percent' => max(0, $reboundPercent),
            'previous_price_copper' => $previousPrice,
            'drop_since_previous_percent' => max(0, $dropSincePreviousPercent),
            'history_days' => $historyDays,
            'has_enough_history' => $historyDays >= self::MIN_HISTORY_DAYS,
            'rows' => $rows,
        ];
    }

    public function getRecommendations(int $limit = 50, array $excludeItemIds = []): array
    {
        $cached = Cache::remember(self::RECOMMENDATIONS_CACHE_KEY, now()->addHours(2), function () {
            return $this->computeRecommendations(200);
        });

        return collect($cached)
            ->reject(fn($r) => in_array($r['item_id'], $excludeItemIds, true))
            ->take($limit)
            ->values()
            ->all();
    }

    public function refreshRecommendationsCache(int $limit = 200): array
    {
        $recommendations = $this->computeRecommendations($limit);
        Cache::put(self::RECOMMENDATIONS_CACHE_KEY, $recommendations, now()->addHours(2));

        return $recommendations;
    }

    public function getRebounds(int $limit = 50, array $excludeItemIds = []): array
    {
        $cached = Cache::remember(self::REBOUNDS_CACHE_KEY, now()->addHours(2), function () {
            return $this->computeRebounds(200);
        });

        return collect($cached)
            ->reject(fn($r) => in_array($r['item_id'], $excludeItemIds, true))
            ->take($limit)
            ->values()
            ->all();
    }

    public function refreshReboundsCache(int $limit = 200): array
    {
        $rebounds = $this->computeRebounds($limit);
        Cache::put(self::REBOUNDS_CACHE_KEY, $rebounds, now()->addHours(2));

        return $rebounds;
    }

    public function getBreakevenDrops(int $limit = 50, array $excludeItemIds = []): array
    {
        $cached = Cache::remember(self::BREAKEVEN_DROPS_CACHE_KEY, now()->addHours(2), function () {
            return $this->computeBreakevenDrops(200);
        });

        return collect($cached)
            ->reject(fn($r) => in_array($r['item_id'], $excludeItemIds, true))
            ->take($limit)
            ->values()
            ->all();
    }

    public function refreshBreakevenDropsCache(int $limit = 200): array
    {
        $drops = $this->computeBreakevenDrops($limit);
        Cache::put(self::BREAKEVEN_DROPS_CACHE_KEY, $drops, now()->addHours(2));

        return $drops;
    }

    protected function computeRecommendations(int $limit): array
    {
        $results = [];

        foreach ($this->activeItemIds() as $itemId) {
            $stats = $this->getStats($itemId);

            if (!$this->passesBaseFilters($stats)) {
                continue;
            }

            if ($stats['discount_percent'] < self::MIN_MOVE_PERCENT) {
                continue;
            }

            $results[] = [
                'item_id' => $itemId,
                'current_price_copper' => $stats['current_price_copper'],
                'median_price_copper' => $stats['median_price_copper'],
                'discount_percent' => $stats['discount_percent'],
                'reason' => $this->buildDiscountReason($stats),
            ];
        }

        usort($results, fn($a, $b) => $b['discount_percent'] <=> $a['discount_percent']);

        return array_slice($results, 0, $limit);
    }

    protected function computeRebounds(int $limit): array
    {
        $results = [];

        foreach ($this->activeItemIds() as $itemId) {
            $stats = $this->getStats($itemId);

            if (!$this->passesBaseFilters($stats)) {
                continue;
            }

            if ($stats['rebound_percent'] < self::MIN_REBOUND_PERCENT) {
                continue;
            }

            $results[] = [
                'item_id' => $itemId,
                'current_price_copper' => $stats['current_price_copper'],
                'recent_min_copper' => $stats['recent_min_copper'],
                'rebound_percent' => $stats['rebound_percent'],
                'reason' => 'Subió ' . $stats['rebound_percent'] . '% desde su mínimo de los últimos ' . self::REBOUND_LOOKBACK_DAYS . ' días',
            ];
        }

        usort($results, fn($a, $b) => $b['rebound_percent'] <=> $a['rebound_percent']);

        return array_slice($results, 0, $limit);
    }

    protected function computeBreakevenDrops(int $limit): array
    {
        $threshold = $this->breakevenPercent();
        $results = [];

        foreach ($this->activeItemIds() as $itemId) {
            $stats = $this->getStats($itemId);

            if (!$this->passesBaseFilters($stats)) {
                continue;
            }

            if (!$stats['previous_price_copper']) {
                continue;
            }

            if ($stats['drop_since_previous_percent'] < $threshold) {
                continue;
            }

            $results[] = [
                'item_id' => $itemId,
                'current_price_copper' => $stats['current_price_copper'],
                'previous_price_copper' => $stats['previous_price_copper'],
                'drop_percent' => $stats['drop_since_previous_percent'],
                'reason' => "Cayó {$stats['drop_since_previous_percent']}% desde el último sync — si vuelve a su precio anterior, ya cubres la comisión del AH",
            ];
        }

        usort($results, fn($a, $b) => $b['drop_percent'] <=> $a['drop_percent']);

        return array_slice($results, 0, $limit);
    }

    protected function activeItemIds()
    {
        return CommodityPriceHistory::where('snapshot_at', '>=', now()->subDay())
            ->distinct()
            ->pluck('item_id');
    }

    protected function passesBaseFilters(?array $stats): bool
    {
        if (!$stats || !$stats['has_enough_history']) {
            return false;
        }

        if ($stats['avg_volume'] < self::MIN_AVG_VOLUME) {
            return false;
        }

        if ($stats['listing_consistency'] < self::MIN_LISTING_CONSISTENCY) {
            return false;
        }

        if ($stats['current_price_copper'] < self::MIN_CURRENT_PRICE_COPPER) {
            return false;
        }

        return true;
    }

    protected function buildDiscountReason(array $stats): string
    {
        if ($stats['discount_percent'] >= 15) {
            return "Precio {$stats['discount_percent']}% bajo el promedio de 7 días";
        }

        $rows = $stats['rows'];
        $midpoint = now()->subDays(3.5);
        $firstHalfVolume = $rows->where('snapshot_at', '<', $midpoint)->avg('listings') ?? 0;
        $secondHalfVolume = $rows->where('snapshot_at', '>=', $midpoint)->avg('listings') ?? 0;

        if ($firstHalfVolume > 0 && $secondHalfVolume < $firstHalfVolume * 0.7) {
            return 'Alta demanda y poca oferta';
        }

        $price6hAgo = $this->nearestPrice($rows, now()->subHours(6));
        if ($price6hAgo) {
            $recentDrop = (($price6hAgo - $stats['current_price_copper']) / $price6hAgo) * 100;
            if ($recentDrop >= 10) {
                return 'Caída rápida en las últimas 6 horas';
            }
        }

        return 'Buen margen potencial de reventa';
    }

    protected function percentile($sortedPrices, int $percentile): int
    {
        $count = $sortedPrices->count();
        if ($count === 0) {
            return 0;
        }
        if ($count === 1) {
            return (int) $sortedPrices->first();
        }

        $index = ($percentile / 100) * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        $weight = $index - $lower;

        $lowerValue = $sortedPrices->get($lower);
        $upperValue = $sortedPrices->get($upper);

        return (int) round($lowerValue + ($upperValue - $lowerValue) * $weight);
    }

    protected function stdDev($prices): float
    {
        $count = $prices->count();
        if ($count < 2) {
            return 0.0;
        }

        $mean = $prices->avg();
        $variance = $prices->map(fn($p) => ($p - $mean) ** 2)->sum() / $count;

        return sqrt($variance);
    }

    protected function nearestPrice($rows, Carbon $target): ?int
    {
        $closest = $rows->sortBy(function ($row) use ($target) {
            return abs(Carbon::parse($row->snapshot_at)->diffInSeconds($target));
        })->first();

        return $closest ? (int) $closest->min_price_copper : null;
    }

    public function estimatePurchase(int $itemId, int $quantity): array
    {
        $listings = \App\Models\CommodityAuction::where('item_id', $itemId)
            ->orderBy('unit_price')
            ->get(['unit_price', 'quantity']);

        $remaining = $quantity;
        $totalCopper = 0;
        $fulfilled = 0;
        $postPurchaseFloor = null;

        foreach ($listings as $listing) {
            if ($remaining <= 0) {
                $postPurchaseFloor = (int) $listing->unit_price;
                break;
            }

            $take = min($remaining, $listing->quantity);
            $totalCopper += $take * $listing->unit_price;
            $fulfilled += $take;
            $remaining -= $take;

            if ($remaining === 0 && $take < $listing->quantity) {
                $postPurchaseFloor = (int) $listing->unit_price;
            }
        }

        $avgUnitCopper = $fulfilled > 0 ? (int) round($totalCopper / $fulfilled) : 0;

        return [
            'requested_quantity' => $quantity,
            'fulfilled_quantity' => $fulfilled,
            'total_copper' => (int) $totalCopper,
            'avg_unit_copper' => $avgUnitCopper,
            'fully_covered' => $fulfilled >= $quantity,
            'post_purchase_floor_copper' => $postPurchaseFloor,
        ];
    }

    public function buildProfitLadder(int $unitPriceCopper, array $stepsGold = [2, 3, 4, 5, 10, 15, 20, 30, 40], int $quantity = 1): array
    {
        $ladder = [];

        foreach ($stepsGold as $stepGold) {
            $sellPrice = $unitPriceCopper + ($stepGold * 10000);
            $totalSaleGross = $sellPrice * $quantity;
            $totalSaleNet = $totalSaleGross * (1 - self::AH_COMMISSION);
            $totalCost = $unitPriceCopper * $quantity;
            $profit = (int) round($totalSaleNet - $totalCost);

            $ladder[] = [
                'step_gold' => $stepGold,
                'sell_price_copper' => $sellPrice,
                'total_sale_copper' => (int) round($totalSaleNet),
                'profit_copper' => $profit,
            ];
        }

        return $ladder;
    }
}