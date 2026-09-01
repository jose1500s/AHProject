<?php

namespace App\Http\Services;

class WowVaultItemLevelService
{
    protected array $dungeonLevelToIlvl = [
        2 => 305, 3 => 305, 4 => 308, 5 => 308,
        6 => 311, 7 => 315, 8 => 315, 9 => 315,
        10 => 318, 11 => 318, 12 => 318,
    ];

    protected array $raidDifficultyToIlvl = [
        'lfr' => 292,
        'normal' => 305,
        'heroic' => 318,
        'mythic' => 335,
    ];

    protected array $worldTierToIlvl = [
        1 => 279, 2 => 282, 3 => 285, 4 => 289,
        5 => 292, 6 => 298, 7 => 302, 8 => 305,
    ];

    public function resolveDungeonIlvl(?int $level): ?int
    {
        if ($level === null) {
            return null;
        }

        if ($level >= 12) {
            return $this->dungeonLevelToIlvl[12];
        }

        return $this->dungeonLevelToIlvl[$level] ?? null;
    }

    public function resolveWorldIlvl(?int $tier): ?int
    {
        if ($tier === null || $tier < 1) {
            return null;
        }

        if ($tier >= 8) {
            return $this->worldTierToIlvl[8];
        }

        return $this->worldTierToIlvl[$tier] ?? null;
    }

    public function resolveRaidIlvl(?string $difficulty): ?int
    {
        if ($difficulty === null) {
            return null;
        }

        return $this->raidDifficultyToIlvl[strtolower($difficulty)] ?? null;
    }
}