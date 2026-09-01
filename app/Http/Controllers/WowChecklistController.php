<?php

namespace App\Http\Controllers;

use App\Models\Profession;
use App\Models\WowCharacter;
use App\Models\WowCharacterConcentration;
use App\Models\WowCharacterVault;
use Illuminate\Http\Request;

class WowChecklistController extends Controller
{
    public function concentrationAll()
    {
        $professionIcons = Profession::pluck('icon_url', 'name');

        $characters = WowCharacter::pluck('name', 'character_key');

        $rows = WowCharacterConcentration::orderBy('profession')
            ->get(['character_key', 'profession', 'quantity', 'max_quantity', 'synced_at'])
            ->map(function ($row) use ($professionIcons, $characters) {
                [$realm, $name] = explode('-', $row->character_key, 2);

                return [
                    'character_key' => $row->character_key,
                    'character_name' => $characters->get($row->character_key) ?? $name,
                    'realm' => $realm,
                    'profession' => $row->profession,
                    'icon_url' => $professionIcons->get($row->profession),
                    'quantity' => $row->quantity,
                    'max_quantity' => $row->max_quantity,
                    'percent' => $row->max_quantity > 0 ? round(($row->quantity / $row->max_quantity) * 100) : 0,
                    'is_max' => $row->quantity >= $row->max_quantity,
                    'synced_at' => $row->synced_at->toIso8601String(),
                ];
            });

        return response()->json(['concentration' => $rows]);
    }

    public function overview(Request $request)
    {
        $characterKey = (string) $request->query('character');

        if (!$characterKey) {
            return response()->json(['vault' => null]);
        }

        $vaultRows = WowCharacterVault::where('character_key', $characterKey)
            ->orderBy('category')
            ->orderBy('slot_index')
            ->get();

        $vault = [
            'raid' => [],
            'dungeon' => [],
            'world' => [],
        ];

        $totalUnlocked = 0;
        $totalSlots = 0;

        foreach ($vaultRows as $row) {
            if (!isset($vault[$row->category])) {
                continue;
            }

            $vault[$row->category][] = [
                'slot_index' => $row->slot_index,
                'threshold' => $row->threshold,
                'progress' => $row->progress,
                'unlocked' => $row->unlocked,
                'level' => $row->level,
                'ilvl' => $row->ilvl,
            ];

            $totalSlots++;
            if ($row->unlocked) {
                $totalUnlocked++;
            }
        }

        return response()->json([
            'vault' => $vaultRows->isEmpty() ? null : [
                'categories' => $vault,
                'unlocked_count' => $totalUnlocked,
                'total_slots' => $totalSlots,
            ],
        ]);
    }

    public function summary()
    {
        $characters = WowCharacter::get(['character_key', 'name', 'realm', 'last_updated_at']);

        $vaultByCharacter = WowCharacterVault::selectRaw('character_key, SUM(CASE WHEN unlocked THEN 1 ELSE 0 END) as unlocked_count, COUNT(*) as total_slots')
            ->groupBy('character_key')
            ->get()
            ->keyBy('character_key');

        $characterVaultStatus = $characters->map(function ($char) use ($vaultByCharacter) {
            $vault = $vaultByCharacter->get($char->character_key);
            $hasRewards = $vault && $vault->unlocked_count > 0;

            return [
                'character_key' => $char->character_key,
                'character_name' => $char->name,
                'realm' => $char->realm,
                'has_rewards' => $hasRewards,
                'unlocked_count' => $vault->unlocked_count ?? 0,
                'total_slots' => $vault->total_slots ?? 0,
            ];
        })->sortByDesc('has_rewards')->values();

        $vaultCharactersWithRewards = $characterVaultStatus->where('has_rewards', true)->count();

        $lastUpdatedAt = $characters->max('last_updated_at');

        return response()->json([
            'characters_tracked' => $characters->count(),
            'vault_progress' => [
                'with_rewards' => $vaultCharactersWithRewards,
                'total' => $characters->count(),
                'characters' => $characterVaultStatus,
            ],
            'tasks_completed' => [
                'done' => 0,
                'total' => 0,
            ],
            'last_updated_at' => $lastUpdatedAt ? $lastUpdatedAt->toIso8601String() : null,
        ]);
    }
}