<?php

namespace App\Http\Controllers;

use App\Models\WowCharacter;
use App\Models\WowWarband;
use App\Models\WowAuctionTransaction;
use App\Models\WowPostFee;
use App\Models\WowActiveAuction;
use App\Models\WowCharacterInventory;
use App\Models\WowCharacterConcentration;
use App\Models\WowCharacterVault;
use App\Models\WowCraftHistory;
use App\Models\FarmNodeEvent;
use App\Models\FarmSession;
use App\Models\WowGoldSnapshot;
use App\Models\WowCharacterGoldSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WowSyncController extends Controller
{
    public function ingest(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token || $token !== config('services.wow_tracker.token')) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $payload = $request->all();

        $charactersIn = $payload['characters'] ?? [];
        $warbandIn = $payload['warband'] ?? null;
        $transactionsIn = $payload['auctionTransactions'] ?? [];
        $feesIn = $payload['postFees'] ?? [];
        $craftHistoryIn = $payload['craftHistory'] ?? [];
        $farmNodeEventsIn = $payload['farmNodeEvents'] ?? [];
        $activeCharacterIn = $payload['activeCharacter'] ?? null;

        if (is_string($activeCharacterIn) && $activeCharacterIn !== '') {
            Cache::put('wow_active_character', $activeCharacterIn, now()->addDays(7));

            $activeSession = FarmSession::where('status', 'active')->first();

            if ($activeSession && $activeSession->character_key !== $activeCharacterIn) {
                $activeSession->update(['character_key' => $activeCharacterIn]);
            }
        }

        $summary = [
            'characters' => 0,
            'transactions_new' => 0,
            'fees_new' => 0,
            'active_auctions' => 0,
            'inventory_items' => 0,
            'crafts_new' => 0,
            'farm_nodes_new' => 0,
            'farm_nodes_merged' => 0,
        ];

        DB::transaction(function () use ($charactersIn, $warbandIn, $transactionsIn, $feesIn, $craftHistoryIn, $farmNodeEventsIn, &$summary) {
            foreach ($charactersIn as $characterKey => $char) {
                $characterSyncedAt = isset($char['lastUpdated'])
                    ? now()->createFromTimestamp($char['lastUpdated'])
                    : now();

                WowCharacter::updateOrCreate(
                    ['character_key' => $characterKey],
                    [
                        'name' => $char['name'] ?? $characterKey,
                        'realm' => $char['realm'] ?? '',
                        'class' => $char['class'] ?? null,
                        'level' => $char['level'] ?? 0,
                        'ilvl' => $char['ilvl'] ?? 0,
                        'gold_copper' => $char['gold'] ?? 0,
                        'last_updated_at' => $characterSyncedAt,
                    ]
                );
                $summary['characters']++;

                WowActiveAuction::where('character_key', $characterKey)->delete();
                $activeAuctions = $char['activeAuctions'] ?? [];
                if (is_array($activeAuctions)) {
                    $rows = collect($activeAuctions)->map(fn($a) => [
                        'character_key' => $characterKey,
                        'item_id' => $a['itemID'] ?? null,
                        'item_name' => $a['itemName'] ?? 'Desconocido',
                        'quantity' => $a['quantity'] ?? 1,
                        'buyout_copper' => $a['buyoutAmount'] ?? 0,
                        'bid_copper' => $a['bidAmount'] ?? 0,
                        'time_left_seconds' => $a['timeLeft'] ?? 0,
                        'num_bids' => $a['numBids'] ?? 0,
                        'synced_at' => now(),
                    ])->all();

                    if (!empty($rows)) {
                        WowActiveAuction::insert($rows);
                        $summary['active_auctions'] += count($rows);
                    }
                }

                WowCharacterInventory::where('character_key', $characterKey)->delete();
                $bags = $char['bags'] ?? [];
                $bank = $char['bank'] ?? [];

                $inventoryRows = [];

                if (is_array($bags)) {
                    foreach ($bags as $item) {
                        $inventoryRows[] = [
                            'character_key' => $characterKey,
                            'location' => 'bag',
                            'item_id' => $item['itemID'] ?? 0,
                            'quantity' => $item['quantity'] ?? 1,
                            'synced_at' => now(),
                        ];
                    }
                }

                if (is_array($bank)) {
                    foreach ($bank as $item) {
                        $inventoryRows[] = [
                            'character_key' => $characterKey,
                            'location' => 'bank',
                            'item_id' => $item['itemID'] ?? 0,
                            'quantity' => $item['quantity'] ?? 1,
                            'synced_at' => now(),
                        ];
                    }
                }

                if (!empty($inventoryRows)) {
                    collect($inventoryRows)->chunk(1000)->each(
                        fn($chunk) => WowCharacterInventory::insert($chunk->all())
                    );
                    $summary['inventory_items'] += count($inventoryRows);
                }

                $concentration = $char['concentration'] ?? [];
                if (is_array($concentration) && !empty($concentration)) {
                    $rows = collect($concentration)->map(fn($data, $profession) => [
                        'character_key' => $characterKey,
                        'profession' => $profession,
                        'currency_id' => $data['currencyID'] ?? 0,
                        'quantity' => $data['quantity'] ?? 0,
                        'max_quantity' => $data['maxQuantity'] ?? 0,
                        'synced_at' => $characterSyncedAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->values()->all();

                    if (!empty($rows)) {
                        WowCharacterConcentration::upsert(
                            $rows,
                            ['character_key', 'profession'],
                            ['currency_id', 'quantity', 'max_quantity', 'synced_at', 'updated_at']
                        );
                    }
                }

                $vault = $char['vault'] ?? [];
                if (is_array($vault) && !empty($vault)) {
                    $rows = [];

                    foreach (['raid', 'dungeon', 'world'] as $category) {
                        $slots = $vault[$category] ?? [];
                        if (!is_array($slots)) {
                            continue;
                        }

                        foreach ($slots as $i => $slot) {
                            $rows[] = [
                                'character_key' => $characterKey,
                                'category' => $category,
                                'slot_index' => $slot['index'] ?? $i,
                                'threshold' => $slot['threshold'] ?? 0,
                                'progress' => $slot['progress'] ?? 0,
                                'unlocked' => $slot['unlocked'] ?? false,
                                'level' => $slot['level'] ?? null,
                                'ilvl' => $slot['ilvl'] ?? null,
                                'synced_at' => $characterSyncedAt,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }

                    if (!empty($rows)) {
                        WowCharacterVault::upsert(
                            $rows,
                            ['character_key', 'category', 'slot_index'],
                            ['threshold', 'progress', 'unlocked', 'level', 'ilvl', 'synced_at', 'updated_at']
                        );
                    }
                }
            }

            if ($warbandIn) {
                WowWarband::updateOrCreate(
                    ['id' => 1],
                    [
                        'gold_copper' => $warbandIn['gold'] ?? 0,
                        'last_updated_at' => isset($warbandIn['lastUpdated'])
                            ? now()->createFromTimestamp($warbandIn['lastUpdated'])
                            : now(),
                    ]
                );
            }

            if (is_array($transactionsIn) && !empty($transactionsIn)) {
                $rows = collect($transactionsIn)->map(fn($tx) => [
                    'character_key' => $tx['character'] ?? '',
                    'source_id' => $tx['id'] ?? null,
                    'type' => $tx['type'] ?? 'sale',
                    'item_name' => $tx['itemName'] ?? 'Desconocido',
                    'item_id' => $tx['itemID'] ?? null,
                    'quantity' => $tx['quantity'] ?? 1,
                    'counterparty' => $tx['counterparty'] ?? null,
                    'sale_price_copper' => $tx['salePrice'] ?? 0,
                    'deposit_copper' => $tx['deposit'] ?? 0,
                    'consignment_copper' => $tx['consignment'] ?? 0,
                    'amount_copper' => $tx['amount'] ?? 0,
                    'occurred_at' => isset($tx['timestamp'])
                        ? now()->createFromTimestamp($tx['timestamp'])
                        : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->filter(fn($row) => $row['source_id'] !== null)->values()->all();

                if (!empty($rows)) {
                    $beforeCount = WowAuctionTransaction::count();

                    WowAuctionTransaction::upsert(
                        $rows,
                        ['character_key', 'source_id'],
                        ['type', 'item_name', 'item_id', 'quantity', 'counterparty', 'sale_price_copper', 'deposit_copper', 'consignment_copper', 'amount_copper', 'occurred_at', 'updated_at']
                    );

                    $afterCount = WowAuctionTransaction::count();
                    $summary['transactions_new'] = $afterCount - $beforeCount;
                }
            }

            if (is_array($feesIn) && !empty($feesIn)) {
                $rows = collect($feesIn)->map(fn($fee) => [
                    'character_key' => $fee['character'] ?? '',
                    'item_name' => $fee['itemName'] ?? 'Desconocido',
                    'fee_copper' => $fee['fee'] ?? 0,
                    'occurred_at' => isset($fee['timestamp'])
                        ? now()->createFromTimestamp($fee['timestamp'])
                        : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                if (!empty($rows)) {
                    $beforeCount = WowPostFee::count();

                    WowPostFee::upsert(
                        $rows,
                        ['character_key', 'item_name', 'fee_copper', 'occurred_at'],
                        ['updated_at']
                    );

                    $afterCount = WowPostFee::count();
                    $summary['fees_new'] = $afterCount - $beforeCount;
                }
            }

            if (is_array($craftHistoryIn) && !empty($craftHistoryIn)) {
                $rows = collect($craftHistoryIn)->map(fn($craft) => [
                    'character_key' => $craft['character'] ?? '',
                    'source_id' => $craft['id'] ?? null,
                    'item_id' => $craft['itemID'] ?? 0,
                    'quantity' => $craft['quantity'] ?? 1,
                    'multicraft' => $craft['multicraft'] ?? 0,
                    'concentration_spent' => $craft['concentrationSpent'] ?? 0,
                    'concentration_currency_id' => $craft['concentrationCurrencyID'] ?? null,
                    'crafting_quality' => $craft['craftingQuality'] ?? null,
                    'is_crit' => $craft['isCrit'] ?? false,
                    'first_craft_reward' => $craft['firstCraftReward'] ?? false,
                    'occurred_at' => isset($craft['timestamp'])
                        ? now()->createFromTimestamp($craft['timestamp'])
                        : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->filter(fn($row) => $row['source_id'] !== null && $row['character_key'] !== '')->values()->all();

                if (!empty($rows)) {
                    $beforeCount = WowCraftHistory::count();

                    WowCraftHistory::upsert(
                        $rows,
                        ['character_key', 'source_id'],
                        ['item_id', 'quantity', 'multicraft', 'concentration_spent', 'concentration_currency_id', 'crafting_quality', 'is_crit', 'first_craft_reward', 'occurred_at', 'updated_at']
                    );

                    $afterCount = WowCraftHistory::count();
                    $summary['crafts_new'] = $afterCount - $beforeCount;
                }
            }

            if (is_array($farmNodeEventsIn) && !empty($farmNodeEventsIn)) {
                $rows = collect($farmNodeEventsIn)->map(fn($event) => [
                    'character_key' => $event['character'] ?? '',
                    'source_id' => $event['id'] ?? null,
                    'profession' => $event['profession'] ?? null,
                    'item_id' => $event['itemID'] ?? null,
                    'quantity' => $event['quantity'] ?? 1,
                    'occurred_at' => isset($event['occurredAt'])
                        ? now()->createFromTimestamp($event['occurredAt'])
                        : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->filter(fn($row) => $row['source_id'] !== null && $row['character_key'] !== '' && $row['item_id'] !== null)
                    ->values()->all();

                if (!empty($rows)) {
                    $beforeCount = FarmNodeEvent::count();

                    FarmNodeEvent::upsert(
                        $rows,
                        ['character_key', 'source_id'],
                        ['profession', 'item_id', 'quantity', 'occurred_at', 'updated_at']
                    );

                    $afterCount = FarmNodeEvent::count();
                    $summary['farm_nodes_new'] = $afterCount - $beforeCount;
                    $summary['farm_nodes_merged'] = count($rows) - $summary['farm_nodes_new'];
                }
            }
        });

        // Snapshot del oro total (personajes + warband) tras cada sync real. Alimenta
        // la grafica de "Evolucion del oro" y el % de cambio semanal en el hero de Mi Oro.
        $charactersGold = WowCharacter::sum('gold_copper');
        $warbandGold = WowWarband::value('gold_copper') ?? 0;

        WowGoldSnapshot::create([
            'characters_gold_copper' => $charactersGold,
            'warband_gold_copper' => $warbandGold,
            'total_gold_copper' => $charactersGold + $warbandGold,
            'snapshot_at' => now(),
        ]);

        // Snapshot de oro INDIVIDUAL por personaje, en el mismo instante. Se recorren
        // TODOS los personajes conocidos (no solo los que vinieron en este payload)
        // para que la grafica por personaje quede consistente con la suma total de
        // arriba, aunque hoy solo se haya sincronizado un personaje especifico.
        $now = now();
        $characterSnapshotRows = WowCharacter::pluck('gold_copper', 'character_key')
            ->map(fn($gold, $characterKey) => [
                'character_key' => $characterKey,
                'gold_copper' => $gold,
                'snapshot_at' => $now,
            ])
            ->values()
            ->all();

        if (!empty($characterSnapshotRows)) {
            WowCharacterGoldSnapshot::insert($characterSnapshotRows);
        }

        return response()->json(['ok' => true, 'summary' => $summary]);
    }
}