<?php

namespace App\Http\Services;

use App\Models\FarmNodeEvent;
use App\Models\FarmSession;
use App\Models\Item;
use App\Models\RecipeReagent;
use App\Models\CommodityPriceHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class FarmSessionValueService
{
    private const RISE_THRESHOLD_PERCENT = 12;
    private const DROP_THRESHOLD_PERCENT = 12;

    public function calculateSessionValue(FarmSession $session): array
    {
        $start = $session->started_at;
        $end = $session->endBoundary();

        $events = FarmNodeEvent::inWindow($session->character_key, $start, $end)
            ->get(['item_id', 'quantity', 'occurred_at', 'profession']);

        $groupedByItem = $events->groupBy('item_id');

        $items = Item::whereIn('blizzard_id', $groupedByItem->keys())
            ->get()
            ->keyBy('blizzard_id');

        $craftQualities = $this->resolveCraftQualities($groupedByItem->keys()->all());

        $breakdown = [];
        $totalBaselineCopper = 0;
        $totalCurrentCopper = 0;
        $unresolvedItemIds = [];

        foreach ($groupedByItem as $itemId => $itemEvents) {
            $totalQuantity = $itemEvents->sum('quantity');
            $profession = $itemEvents->first()->profession;

            $baselineCopper = 0;
            $hasBaselinePrice = false;

            foreach ($itemEvents as $event) {
                $priceAtCollection = $this->priceAtOrBefore($itemId, Carbon::parse($event->occurred_at));

                if ($priceAtCollection !== null) {
                    $baselineCopper += $priceAtCollection * $event->quantity;
                    $hasBaselinePrice = true;
                }
            }

            $latest = $this->latestPrice($itemId);
            $currentUnitCopper = $latest['price'] ?? null;
            $currentCopper = $currentUnitCopper !== null ? $currentUnitCopper * $totalQuantity : null;

            if (!$hasBaselinePrice || $currentUnitCopper === null) {
                $unresolvedItemIds[] = $itemId;
            }

            $totalBaselineCopper += $baselineCopper;
            $totalCurrentCopper += $currentCopper ?? 0;

            $item = $items->get($itemId);

            $breakdown[] = [
                'item_id' => (int) $itemId,
                'item_name' => $item?->name ?? "Ítem #{$itemId}",
                'icon_url' => $item?->icon_url,
                'craft_quality' => $craftQualities[$itemId] ?? null,
                'profession' => $profession,
                'quantity' => $totalQuantity,
                'baseline_total_copper' => $baselineCopper,
                'current_unit_copper' => $currentUnitCopper,
                'current_total_copper' => $currentCopper,
                'has_price_data' => $hasBaselinePrice && $currentUnitCopper !== null,
            ];
        }

        usort($breakdown, fn($a, $b) => ($b['current_total_copper'] ?? 0) <=> ($a['current_total_copper'] ?? 0));

        $deltaPercent = $totalBaselineCopper > 0
            ? round((($totalCurrentCopper - $totalBaselineCopper) / $totalBaselineCopper) * 100, 1)
            : 0.0;

        $recommendation = 'estable';
        if ($deltaPercent <= -self::DROP_THRESHOLD_PERCENT) {
            $recommendation = 'vender_ahora';
        } elseif ($deltaPercent >= self::RISE_THRESHOLD_PERCENT) {
            $recommendation = 'esperar';
        }

        return [
            'session_id' => $session->id,
            'character_key' => $session->character_key,
            'started_at' => $start->toIso8601String(),
            'ended_at' => $end->toIso8601String(),
            'total_nodes' => $events->count(),
            'total_items_types' => $groupedByItem->count(),
            'baseline_value_copper' => (int) round($totalBaselineCopper),
            'current_value_copper' => (int) round($totalCurrentCopper),
            'delta_percent' => $deltaPercent,
            'recommendation' => $recommendation,
            'has_unresolved_items' => !empty($unresolvedItemIds),
            'unresolved_item_ids' => $unresolvedItemIds,
            'staleness_seconds' => $this->stalenessSeconds(),
            'items' => $breakdown,
        ];
    }

    protected function resolveCraftQualities(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $goldFromReagents = RecipeReagent::whereIn('item_id_high', $itemIds)
            ->pluck('item_id_high')->all();

        $silverFromReagents = RecipeReagent::whereIn('item_id', $itemIds)
            ->whereNotNull('item_id_high')
            ->pluck('item_id')->all();

        $goldIds = array_unique($goldFromReagents);
        $silverIds = array_unique($silverFromReagents);

        $map = [];
        foreach ($itemIds as $id) {
            if (in_array($id, $goldIds)) {
                $map[$id] = 'gold';
            } elseif (in_array($id, $silverIds)) {
                $map[$id] = 'silver';
            } else {
                $map[$id] = null;
            }
        }

        return $map;
    }

    protected function priceAtOrBefore(int $itemId, Carbon $time): ?int
    {
        $row = CommodityPriceHistory::where('item_id', $itemId)
            ->where('snapshot_at', '<=', $time)
            ->orderByDesc('snapshot_at')
            ->first();

        if (!$row) {
            return null;
        }

        return (int) ($row->liquid_price_copper ?? $row->min_price_copper);
    }

    protected function latestPrice(int $itemId): ?array
    {
        $row = CommodityPriceHistory::where('item_id', $itemId)
            ->orderByDesc('snapshot_at')
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'price' => (int) ($row->liquid_price_copper ?? $row->min_price_copper),
            'snapshot_at' => $row->snapshot_at,
        ];
    }

    protected function stalenessSeconds(): ?int
    {
        $lastModified = Cache::get('commodities_last_modified');

        if (!$lastModified) {
            return null;
        }

        return (int) round(Carbon::parse($lastModified)->diffInSeconds(now()));
    }
}