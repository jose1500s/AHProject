<?php

namespace App\Http\Services;

use App\Models\CommodityPriceAlert;
use App\Models\CommodityWatchlist;
use App\Models\Item;
use Illuminate\Support\Facades\Http;

class CommodityAlertService
{
    private const DISCOUNT_THRESHOLD = 0.65;
    private const COOLDOWN_HOURS = 12;

    public function __construct(
        protected CommodityMarketAnalysisService $analysisService
    ) {
    }

    public function checkWatchlist(): array
    {
        $triggered = [];

        foreach (CommodityWatchlist::where('enabled', true)->get() as $entry) {
            $result = $this->checkItem($entry->item_id);

            if ($result) {
                $triggered[] = $result;
            }
        }

        return $triggered;
    }

    protected function checkItem(int $itemId): ?array
    {
        $stats = $this->analysisService->getStats($itemId);

        if (!$stats) {
            return null;
        }

        $threshold = $stats['median_price_copper'] * self::DISCOUNT_THRESHOLD;

        if ($stats['current_price_copper'] > $threshold) {
            return null;
        }

        $lastAlert = CommodityPriceAlert::where('item_id', $itemId)
            ->orderByDesc('created_at')
            ->first();

        if ($lastAlert && $lastAlert->created_at->gt(now()->subHours(self::COOLDOWN_HOURS))) {
            return null;
        }

        $percentBelow = round((($stats['median_price_copper'] - $stats['current_price_copper']) / $stats['median_price_copper']) * 100, 1);

        CommodityPriceAlert::create([
            'item_id' => $itemId,
            'triggered_price_copper' => $stats['current_price_copper'],
            'median_price_copper' => $stats['median_price_copper'],
            'percent_below' => $percentBelow,
        ]);

        $item = Item::where('blizzard_id', $itemId)->first();
        $this->sendDiscordAlert($item, $itemId, $stats['current_price_copper'], $stats['median_price_copper'], $percentBelow);

        return ['item_id' => $itemId, 'percent_below' => $percentBelow];
    }

    protected function sendDiscordAlert(?Item $item, int $itemId, int $currentPrice, int $medianPrice, float $percentBelow): void
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (!$webhookUrl) {
            return;
        }

        $name = $item?->name ?? "Ítem #{$itemId}";

        Http::post($webhookUrl, [
            'embeds' => [[
                'title' => "🔥 {$name} está muy barato",
                'description' => 'Caída fuerte de precio en tu watchlist — margen amplio, buena oportunidad de comprar en volumen.',
                'color' => 3066993,
                'fields' => [
                    ['name' => 'Precio actual', 'value' => $this->formatGold($currentPrice), 'inline' => true],
                    ['name' => 'Precio normal (7d)', 'value' => $this->formatGold($medianPrice), 'inline' => true],
                    ['name' => '% por debajo', 'value' => "{$percentBelow}%", 'inline' => true],
                ],
            ]],
        ]);
    }

    protected function formatGold(int $copper): string
    {
        $gold = intdiv($copper, 10000);
        $silver = intdiv($copper % 10000, 100);
        $rest = $copper % 100;

        return "{$gold}g {$silver}s {$rest}c";
    }
}