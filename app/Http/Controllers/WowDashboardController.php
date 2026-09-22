<?php

namespace App\Http\Controllers;

use App\Http\Services\BlizzApiService;
use App\Models\Item;
use App\Models\WowCharacter;
use App\Models\WowWarband;
use App\Models\WowActiveAuction;
use App\Models\WowAuctionTransaction;
use App\Models\WowGoldSnapshot;
use App\Models\WowCharacterGoldSnapshot;
use App\Models\WowGoldGoal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WowDashboardController extends Controller
{
    private const LOCAL_TIMEZONE = 'America/Mexico_City';

    private array $classIconMap = [
        'WARRIOR' => 'warrior', 'PALADIN' => 'paladin', 'HUNTER' => 'hunter',
        'ROGUE' => 'rogue', 'PRIEST' => 'priest', 'DEATHKNIGHT' => 'deathknight',
        'SHAMAN' => 'shaman', 'MAGE' => 'mage', 'WARLOCK' => 'warlock',
        'MONK' => 'monk', 'DRUID' => 'druid', 'DEMONHUNTER' => 'demonhunter',
        'EVOKER' => 'evoker',
    ];

    private function formatCoin(int $copper): array
    {
        return BlizzApiService::copperToGsc($copper);
    }

    private function classIconUrl(?string $class): ?string
    {
        if (!$class) {
            return null;
        }

        $key = strtoupper(str_replace([' ', '-'], '', $class));
        $slug = $this->classIconMap[$key] ?? null;

        return $slug ? "https://wow.zamimg.com/images/wow/icons/medium/classicon_{$slug}.jpg" : null;
    }

    private function resolveItemMeta(?int $itemId, ?string $fallbackName): array
    {
        $item = null;

        if ($itemId) {
            $item = Item::where('blizzard_id', $itemId)->first();
        }

        if (!$item && $fallbackName) {
            $item = Item::where('name', $fallbackName)->first();
        }

        if (!$item) {
            return ['name' => $fallbackName ?? "Ítem #{$itemId}", 'icon_url' => null, 'quality' => null];
        }

        $isGenericName = !$fallbackName
            || $fallbackName === 'Desconocido'
            || str_starts_with($fallbackName, 'Item #')
            || str_starts_with($fallbackName, 'Ítem #');

        return [
            'name' => $isGenericName ? $item->name : $fallbackName,
            'icon_url' => $item->icon_url ?: null,
            'quality' => $item->quality,
        ];
    }

    public function characters()
    {
        $characters = WowCharacter::orderBy('name')->get()->map(fn($c) => [
            'key' => $c->character_key,
            'name' => $c->name,
            'realm' => $c->realm,
            'class' => $c->class,
            'class_icon' => $this->classIconUrl($c->class),
            'level' => $c->level,
            'ilvl' => $c->ilvl,
            'gold' => $this->formatCoin($c->gold_copper),
        ]);

        $warband = WowWarband::first();

        return response()->json([
            'characters' => $characters,
            'warband' => $warband ? $this->formatCoin($warband->gold_copper) : null,
        ]);
    }

    public function overview(Request $request)
    {
        $characterKey = $request->query('character');

        $salesQuery = WowAuctionTransaction::where('type', 'sale');
        $purchasesQuery = WowAuctionTransaction::where('type', 'purchase');

        if ($characterKey) {
            $salesQuery->where('character_key', $characterKey);
            $purchasesQuery->where('character_key', $characterKey);
        }

        $totalEarned = (clone $salesQuery)->sum('amount_copper');
        $totalSpent = (clone $purchasesQuery)->sum(DB::raw('abs(amount_copper)'));
        $salesCount = (clone $salesQuery)->count();
        $purchasesCount = (clone $purchasesQuery)->count();

        $todayStart = Carbon::now(self::LOCAL_TIMEZONE)->startOfDay()->setTimezone('UTC');
        $todayEarned = (clone $salesQuery)->where('occurred_at', '>=', $todayStart)->sum('amount_copper');
        $todaySpent = (clone $purchasesQuery)->where('occurred_at', '>=', $todayStart)->sum(DB::raw('abs(amount_copper)'));
        $todaySalesCount = (clone $salesQuery)->where('occurred_at', '>=', $todayStart)->count();
        $todayNetProfit = $todayEarned - $todaySpent;

        $activeAuctionsQuery = WowActiveAuction::query();
        if ($characterKey) {
            $activeAuctionsQuery->where('character_key', $characterKey);
        }
        $invested = (clone $activeAuctionsQuery)->sum(DB::raw('quantity * GREATEST(buyout_copper, bid_copper)'));
        $activeCount = (clone $activeAuctionsQuery)->count();

        $bestFlip = (clone $salesQuery)->orderByDesc('amount_copper')->first();
        $bestFlipMeta = $bestFlip ? $this->resolveItemMeta($bestFlip->item_id, $bestFlip->item_name) : null;

        $currentGold = $characterKey
            ? (WowCharacter::where('character_key', $characterKey)->value('gold_copper') ?? 0)
            : WowCharacter::sum('gold_copper');

        $firstSaleAt = (clone $salesQuery)->min('occurred_at');
        $daysSinceFirstSale = $firstSaleAt
            ? max(1, Carbon::parse($firstSaleAt)->diffInDays(now()) + 1)
            : 1;
        $avgDailyEarned = $totalEarned > 0 ? intdiv($totalEarned, $daysSinceFirstSale) : 0;

        return response()->json([
            'current_gold' => $this->formatCoin($currentGold),
            'total_earned' => $this->formatCoin($totalEarned),
            'total_earned_count' => $salesCount,
            'total_spent' => $this->formatCoin($totalSpent),
            'total_spent_count' => $purchasesCount,
            'net_profit' => $this->formatCoin($totalEarned - $totalSpent),
            'invested' => $this->formatCoin($invested),
            'invested_count' => $activeCount,
            'best_flip' => $bestFlip ? [
                'item_name' => $bestFlipMeta['name'],
                'icon_url' => $bestFlipMeta['icon_url'],
                'amount' => $this->formatCoin($bestFlip->amount_copper),
            ] : null,
            'today_earned' => $this->formatCoin($todayEarned),
            'today_spent' => $this->formatCoin($todaySpent),
            'today_sales_count' => $todaySalesCount,
            'today_net_profit' => $this->formatCoin($todayNetProfit),
            'avg_daily_earned' => $this->formatCoin($avgDailyEarned),
            'avg_daily_days' => $daysSinceFirstSale,
        ]);
    }

    public function goldHistory(Request $request)
    {
        $range = $request->query('range', '7d');
        $characterKey = $request->query('character');

        $days = match ($range) {
            '1d' => 1,
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $since = Carbon::now(self::LOCAL_TIMEZONE)->subDays($days)->setTimezone('UTC');
        $weekAgo = Carbon::now(self::LOCAL_TIMEZONE)->subDays(7)->setTimezone('UTC');

        if ($characterKey) {
            $snapshots = WowCharacterGoldSnapshot::where('character_key', $characterKey)
                ->where('snapshot_at', '>=', $since)
                ->orderBy('snapshot_at')
                ->get(['gold_copper', 'snapshot_at']);

            $latest = $snapshots->last();
            $currentTotal = $latest ? $latest->gold_copper
                : (WowCharacter::where('character_key', $characterKey)->value('gold_copper') ?? 0);

            $weekAgoSnapshot = WowCharacterGoldSnapshot::where('character_key', $characterKey)
                ->where('snapshot_at', '<=', $weekAgo)
                ->orderByDesc('snapshot_at')
                ->first();

            $weekChangePercent = null;
            if ($weekAgoSnapshot && $weekAgoSnapshot->gold_copper > 0) {
                $weekChangePercent = round(
                    (($currentTotal - $weekAgoSnapshot->gold_copper) / $weekAgoSnapshot->gold_copper) * 100,
                    1
                );
            }

            $dailyBars = [];
            for ($i = 6; $i >= 0; $i--) {
                $dayStart = Carbon::now(self::LOCAL_TIMEZONE)->subDays($i)->startOfDay()->setTimezone('UTC');
                $dayEnd = Carbon::now(self::LOCAL_TIMEZONE)->subDays($i)->endOfDay()->setTimezone('UTC');

                $dayLast = WowCharacterGoldSnapshot::where('character_key', $characterKey)
                    ->whereBetween('snapshot_at', [$dayStart, $dayEnd])
                    ->orderByDesc('snapshot_at')
                    ->value('gold_copper');

                $dailyBars[] = $dayLast ?? 0;
            }

            return response()->json([
                'series' => $snapshots->map(fn($s) => [
                    'snapshot_at' => $s->snapshot_at->toIso8601String(),
                    'total_gold_copper' => $s->gold_copper,
                ]),
                'current_total_copper' => $currentTotal,
                'current_available_copper' => $currentTotal,
                'current_warband_copper' => null,
                'week_change_percent' => $weekChangePercent,
                'daily_bars' => $dailyBars,
            ]);
        }

        $snapshots = WowGoldSnapshot::where('snapshot_at', '>=', $since)
            ->orderBy('snapshot_at')
            ->get(['total_gold_copper', 'characters_gold_copper', 'warband_gold_copper', 'snapshot_at']);

        $latest = $snapshots->last();
        $currentTotal = $latest
            ? $latest->total_gold_copper
            : (WowCharacter::sum('gold_copper') + (WowWarband::value('gold_copper') ?? 0));

        $weekAgoSnapshot = WowGoldSnapshot::where('snapshot_at', '<=', $weekAgo)
            ->orderByDesc('snapshot_at')
            ->first();

        $weekChangePercent = null;
        if ($weekAgoSnapshot && $weekAgoSnapshot->total_gold_copper > 0) {
            $weekChangePercent = round(
                (($currentTotal - $weekAgoSnapshot->total_gold_copper) / $weekAgoSnapshot->total_gold_copper) * 100,
                1
            );
        }

        $dailyBars = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayStart = Carbon::now(self::LOCAL_TIMEZONE)->subDays($i)->startOfDay()->setTimezone('UTC');
            $dayEnd = Carbon::now(self::LOCAL_TIMEZONE)->subDays($i)->endOfDay()->setTimezone('UTC');

            $dayLast = WowGoldSnapshot::whereBetween('snapshot_at', [$dayStart, $dayEnd])
                ->orderByDesc('snapshot_at')
                ->value('total_gold_copper');

            $dailyBars[] = $dayLast ?? 0;
        }

        return response()->json([
            'series' => $snapshots->map(fn($s) => [
                'snapshot_at' => $s->snapshot_at->toIso8601String(),
                'total_gold_copper' => $s->total_gold_copper,
            ]),
            'current_total_copper' => $currentTotal,
            'current_available_copper' => $latest ? $latest->characters_gold_copper : WowCharacter::sum('gold_copper'),
            'current_warband_copper' => $latest ? $latest->warband_gold_copper : (WowWarband::value('gold_copper') ?? 0),
            'week_change_percent' => $weekChangePercent,
            'daily_bars' => $dailyBars,
        ]);
    }

    public function goldGoal()
    {
        $goal = WowGoldGoal::first();

        if (!$goal) {
            return response()->json(['goal' => null]);
        }

        $currentGold = WowCharacter::sum('gold_copper') + (WowWarband::value('gold_copper') ?? 0);
        $remaining = max(0, $goal->target_gold_copper - $currentGold);
        $percent = $goal->target_gold_copper > 0
            ? min(100, round(($currentGold / $goal->target_gold_copper) * 100, 1))
            : 0;

        $daysRemaining = null;
        if ($goal->deadline_date) {
            $today = Carbon::now(self::LOCAL_TIMEZONE)->startOfDay();
            $deadline = Carbon::parse($goal->deadline_date)->startOfDay();
            $daysRemaining = $today->diffInDays($deadline, false);
        }

        return response()->json([
            'goal' => [
                'id' => $goal->id,
                'title' => $goal->title,
                'target_gold' => $this->formatCoin($goal->target_gold_copper),
                'target_gold_number' => intdiv($goal->target_gold_copper, 10000),
                'deadline_date' => $goal->deadline_date?->toDateString(),
                'days_remaining' => $daysRemaining,
                'remaining' => $this->formatCoin($remaining),
                'percent_achieved' => $percent,
            ],
        ]);
    }

    public function updateGoldGoal(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'target_gold' => 'required|integer|min:1',
            'deadline_date' => 'nullable|date',
        ]);

        WowGoldGoal::updateOrCreate(
            ['id' => 1],
            [
                'title' => $data['title'],
                'target_gold_copper' => $data['target_gold'] * 10000,
                'deadline_date' => $data['deadline_date'] ?? null,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function activeAuctions(Request $request)
    {
        $characterKey = $request->query('character');

        $query = WowActiveAuction::query();
        if ($characterKey) {
            $query->where('character_key', $characterKey);
        }

        $auctions = $query->orderBy('time_left_seconds')->get()->map(function ($a) {
            $meta = $this->resolveItemMeta($a->item_id, $a->item_name);
            $unitPrice = max($a->buyout_copper, $a->bid_copper);

            return [
                'item_id' => $a->item_id,
                'item_name' => $meta['name'],
                'icon_url' => $meta['icon_url'],
                'quantity' => $a->quantity,
                'buyout' => $this->formatCoin($a->buyout_copper),
                'bid' => $this->formatCoin($a->bid_copper),
                'total' => $this->formatCoin($unitPrice * $a->quantity),
                'num_bids' => $a->num_bids,
                'time_left_seconds' => $a->time_left_seconds,
            ];
        });

        return response()->json(['auctions' => $auctions]);
    }

    public function transactions(Request $request)
    {
        $characterKey = $request->query('character');
        $type = $request->query('type', 'all');

        $query = WowAuctionTransaction::orderByDesc('occurred_at');

        if ($characterKey) {
            $query->where('character_key', $characterKey);
        }

        if ($type === 'sales') {
            $query->where('type', 'sale');
        } elseif ($type === 'purchases') {
            $query->where('type', 'purchase');
        }

        $transactions = $query->paginate(20);

        $transactions->getCollection()->transform(function ($tx) {
            $meta = $this->resolveItemMeta($tx->item_id, $tx->item_name);

            return [
                'id' => $tx->id,
                'type' => $tx->type,
                'item_name' => $meta['name'],
                'icon_url' => $meta['icon_url'],
                'item_id' => $tx->item_id,
                'quantity' => $tx->quantity,
                'counterparty' => $tx->counterparty,
                'amount' => $this->formatCoin(abs($tx->amount_copper)),
                'occurred_at' => $tx->occurred_at,
            ];
        });

        return response()->json($transactions);
    }

    public function salesByItem(Request $request)
    {
        $characterKey = $request->query('character');

        $query = WowAuctionTransaction::where('type', 'sale')->orderByDesc('occurred_at');
        if ($characterKey) {
            $query->where('character_key', $characterKey);
        }

        $sales = $query->get();

        $grouped = $sales->groupBy('item_name')->map(function ($group, $itemName) {
            $first = $group->first();
            $meta = $this->resolveItemMeta($first->item_id, $itemName);
            $totalCopper = $group->sum('amount_copper');

            return [
                'item_name' => $meta['name'],
                'icon_url' => $meta['icon_url'],
                'quality' => $meta['quality'],
                'sales_count' => $group->count(),
                'total_copper' => $totalCopper,
                'total' => $this->formatCoin($totalCopper),
                'sales' => $group->map(fn($tx) => [
                    'id' => $tx->id,
                    'counterparty' => $tx->counterparty,
                    'amount' => $this->formatCoin($tx->amount_copper),
                    'occurred_at' => $tx->occurred_at,
                ])->values(),
            ];
        })->sortByDesc('total_copper')->values();

        return response()->json(['items' => $grouped]);
    }
}