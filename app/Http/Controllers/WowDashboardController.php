<?php

namespace App\Http\Controllers;

use App\Http\Services\BlizzApiService;
use App\Models\Item;
use App\Models\WowCharacter;
use App\Models\WowWarband;
use App\Models\WowActiveAuction;
use App\Models\WowAuctionTransaction;
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

        // "hoy" se calcula en la zona horaria del jugador, no en la del servidor
        // (evita que ventas de las últimas horas del día se cuenten como "ayer"
        // si el servidor guarda todo en UTC pero el jugador está en otro huso)
        $todayStart = Carbon::now(self::LOCAL_TIMEZONE)->startOfDay();
        $todayEarned = (clone $salesQuery)->where('occurred_at', '>=', $todayStart)->sum('amount_copper');
        $todaySpent = (clone $purchasesQuery)->where('occurred_at', '>=', $todayStart)->sum(DB::raw('abs(amount_copper)'));

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
        ]);
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