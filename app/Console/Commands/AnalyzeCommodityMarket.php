<?php

namespace App\Console\Commands;

use App\Http\Services\CommodityAlertService;
use App\Http\Services\CommodityMarketAnalysisService;
use App\Models\CommodityPriceHistory;
use App\Models\CommodityWatchlist;
use App\Models\Item;
use Illuminate\Console\Command;

class AnalyzeCommodityMarket extends Command
{
    protected $signature = 'commodities:analyze-market';
    protected $description = 'Recalcula recomendaciones/caídas/rebotes de mercado y evalúa alertas de precio bajo de la watchlist';

    public function handle(CommodityMarketAnalysisService $analysisService, CommodityAlertService $alertService)
    {
        $this->info('=== Auction Terminal — Análisis de mercado de commodities ===');
        $this->newLine();

        $activeItemIds = CommodityPriceHistory::where('snapshot_at', '>=', now()->subDay())
            ->distinct()
            ->pluck('item_id');

        $this->info("Items activos en las últimas 24h: {$activeItemIds->count()}");
        $this->newLine();

        $this->info('Calculando recomendaciones (precio bajo)...');
        $recommendations = $analysisService->refreshRecommendationsCache();

        if (empty($recommendations)) {
            $this->warn('No se generó ninguna recomendación con los filtros actuales.');
        } else {
            $itemNames = Item::whereIn('blizzard_id', collect($recommendations)->pluck('item_id'))
                ->pluck('name', 'blizzard_id');

            $this->info(count($recommendations) . ' recomendaciones generadas. Top 15:');
            $this->table(
                ['Item', 'Precio actual', 'Mediana 7d', 'Descuento %', 'Razón'],
                collect($recommendations)->take(15)->map(fn($r) => [
                    $itemNames->get($r['item_id'], "Ítem #{$r['item_id']}"),
                    $r['current_price_copper'],
                    $r['median_price_copper'],
                    $r['discount_percent'] . '%',
                    $r['reason'],
                ])
            );
        }

        $this->newLine();
        $this->info('Calculando caídas desde el último sync (>= punto de equilibrio)...');
        $breakevenDrops = $analysisService->refreshBreakevenDropsCache();

        if (empty($breakevenDrops)) {
            $this->warn('No se detectó ninguna caída relevante en el último sync.');
        } else {
            $itemNames = Item::whereIn('blizzard_id', collect($breakevenDrops)->pluck('item_id'))
                ->pluck('name', 'blizzard_id');

            $this->info(count($breakevenDrops) . ' caídas detectadas. Top 15:');
            $this->table(
                ['Item', 'Precio actual', 'Precio anterior', 'Caída %'],
                collect($breakevenDrops)->take(15)->map(fn($r) => [
                    $itemNames->get($r['item_id'], "Ítem #{$r['item_id']}"),
                    $r['current_price_copper'],
                    $r['previous_price_copper'],
                    $r['drop_percent'] . '%',
                ])
            );
        }

        $this->newLine();
        $this->info('Calculando recuperaciones desde mínimo reciente (4 días)...');
        $rebounds = $analysisService->refreshReboundsCache();

        if (empty($rebounds)) {
            $this->warn('No se detectó ninguna recuperación relevante.');
        } else {
            $itemNames = Item::whereIn('blizzard_id', collect($rebounds)->pluck('item_id'))
                ->pluck('name', 'blizzard_id');

            $this->info(count($rebounds) . ' recuperaciones detectadas. Top 15:');
            $this->table(
                ['Item', 'Precio actual', 'Mínimo 4d', 'Rebote %'],
                collect($rebounds)->take(15)->map(fn($r) => [
                    $itemNames->get($r['item_id'], "Ítem #{$r['item_id']}"),
                    $r['current_price_copper'],
                    $r['recent_min_copper'],
                    $r['rebound_percent'] . '%',
                ])
            );
        }

        $this->newLine();
        $this->info('Evaluando watchlist para alertas de Discord...');

        $watchlistCount = CommodityWatchlist::where('enabled', true)->count();
        $this->info("Items en watchlist activa: {$watchlistCount}");

        if ($watchlistCount === 0) {
            $this->warn('La watchlist está vacía, no hay nada que evaluar.');
        } else {
            $triggered = $alertService->checkWatchlist();

            if (empty($triggered)) {
                $this->info('Ningún item de la watchlist cruzó el umbral de alerta (50% bajo la mediana), o ya se envió una alerta reciente (cooldown de 12h).');
            } else {
                $itemNames = Item::whereIn('blizzard_id', collect($triggered)->pluck('item_id'))
                    ->pluck('name', 'blizzard_id');

                foreach ($triggered as $t) {
                    $name = $itemNames->get($t['item_id'], "Ítem #{$t['item_id']}");
                    $this->info("  🔥 ALERTA enviada: {$name} — {$t['percent_below']}% bajo la mediana");
                }
            }
        }

        $this->newLine();
        $this->info('=== Análisis completado ===');

        return self::SUCCESS;
    }
}