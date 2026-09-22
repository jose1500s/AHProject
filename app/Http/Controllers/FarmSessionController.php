<?php

namespace App\Http\Controllers;

use App\Http\Services\FarmSessionValueService;
use App\Models\FarmNodeEvent;
use App\Models\FarmSession;
use App\Models\WowCharacter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class FarmSessionController extends Controller
{
    public function __construct(
        protected FarmSessionValueService $valueService
    ) {
    }

    protected function resolveActiveCharacterKey(): ?string
    {
        $fromAddon = Cache::get('wow_active_character');

        if ($fromAddon) {
            return $fromAddon;
        }

        return WowCharacter::orderByDesc('last_updated_at')->value('character_key');
    }

    public function detectedCharacter()
    {
        return response()->json(['character_key' => $this->resolveActiveCharacterKey()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|in:timed,freeform',
            'planned_duration_minutes' => 'nullable|integer|min:1',
        ]);

        $characterKey = $this->resolveActiveCharacterKey();

        if (!$characterKey) {
            return response()->json(['error' => 'No se pudo detectar un personaje activo. Sincroniza el addon primero.'], 422);
        }

        $existingActive = FarmSession::where('status', 'active')->first();

        if ($existingActive) {
            if ($existingActive->character_key === $characterKey) {
                return response()->json([
                    'session' => $this->formatSession($existingActive),
                    'already_active' => true,
                ]);
            }

            $existingActive->update([
                'status' => 'stopped',
                'stopped_at' => now(),
            ]);
        }

        $session = FarmSession::create([
            'character_key' => $characterKey,
            'mode' => $data['mode'],
            'status' => 'active',
            'planned_duration_minutes' => $data['planned_duration_minutes'] ?? null,
            'started_at' => now(),
            'stopped_at' => null,
        ]);

        return response()->json(['session' => $this->formatSession($session)]);
    }

    public function stop(int $id)
    {
        $session = FarmSession::findOrFail($id);

        if ($session->status === 'stopped') {
            return response()->json(['session' => $this->formatSession($session), 'already_stopped' => true]);
        }

        $session->update([
            'status' => 'stopped',
            'stopped_at' => now(),
        ]);

        return response()->json(['session' => $this->formatSession($session)]);
    }

    public function index()
    {
        $sessions = FarmSession::orderByDesc('started_at')->limit(20)->get();

        $rows = $sessions->map(function ($session) {
            $formatted = $this->formatSession($session);

            if ($session->status === 'stopped') {
                $value = $this->valueService->calculateSessionValue($session);
                $formatted['delta_percent'] = $value['delta_percent'];
                $formatted['recommendation'] = $value['recommendation'];
                $formatted['current_value_copper'] = $value['current_value_copper'];
                $formatted['total_nodes'] = $value['total_nodes'];
            }

            return $formatted;
        });

        return response()->json(['sessions' => $rows]);
    }

    public function show(int $id)
    {
        $session = FarmSession::findOrFail($id);
        $value = $this->valueService->calculateSessionValue($session);

        return response()->json([
            'session' => $this->formatSession($session),
            'value' => $value,
        ]);
    }

    public function stats(int $id)
    {
        $session = FarmSession::findOrFail($id);

        $start = $session->started_at;
        $end = $session->endBoundary();

        $events = FarmNodeEvent::inWindow($session->character_key, $start, $end)
            ->orderBy('occurred_at')
            ->get(['item_id', 'quantity', 'occurred_at']);

        $totalNodes = $events->count();
        $durationSeconds = max(1, $start->diffInSeconds($end));
        $nodesPerHour = round($totalNodes / ($durationSeconds / 3600), 1);
        $avgSecondsPerNode = $totalNodes > 0 ? round($durationSeconds / $totalNodes, 1) : 0;

        $value = $this->valueService->calculateSessionValue($session);
        $avgGoldPerNode = $totalNodes > 0
            ? round(($value['current_value_copper'] / $totalNodes) / 10000, 2)
            : 0;

        $bucketMinutes = 5;
        $buckets = [];
        foreach ($events as $event) {
            $elapsedMinutes = (int) round($start->diffInMinutes(Carbon::parse($event->occurred_at)));
            $bucketIndex = intdiv($elapsedMinutes, $bucketMinutes);
            $buckets[$bucketIndex] = ($buckets[$bucketIndex] ?? 0) + 1;
        }
        ksort($buckets);
        $paceChart = collect($buckets)->map(fn($count, $index) => [
            'label' => ($index * $bucketMinutes) . '-' . (($index + 1) * $bucketMinutes) . 'min',
            'nodes' => $count,
        ])->values();

        $unitPriceByItem = collect($value['items'])->pluck('current_unit_copper', 'item_id');

        $bucketValues = [];
        foreach ($events as $event) {
            $elapsedMinutes = (int) round($start->diffInMinutes(Carbon::parse($event->occurred_at)));
            $bucketIndex = intdiv($elapsedMinutes, $bucketMinutes);
            $unitPrice = $unitPriceByItem[$event->item_id] ?? null;

            if ($unitPrice === null) {
                continue;
            }

            $bucketValues[$bucketIndex] = ($bucketValues[$bucketIndex] ?? 0) + ($unitPrice * $event->quantity);
        }

        $maxBucketIndex = empty($buckets) ? -1 : max(array_keys($buckets));
        $cumulativeValueCopper = 0;
        $goldPerHourChart = [];

        for ($i = 0; $i <= $maxBucketIndex; $i++) {
            $cumulativeValueCopper += $bucketValues[$i] ?? 0;
            $elapsedHours = (($i + 1) * $bucketMinutes) / 60;
            $goldPerHour = $elapsedHours > 0 ? round(($cumulativeValueCopper / 10000) / $elapsedHours, 1) : 0;

            $goldPerHourChart[] = [
                'label' => ($i * $bucketMinutes) . '-' . (($i + 1) * $bucketMinutes) . 'min',
                'gold_per_hour' => $goldPerHour,
            ];
        }

        $totalQuantity = $events->sum('quantity');
        $itemMix = collect($value['items'])->map(fn($item) => [
            'item_id' => $item['item_id'],
            'item_name' => $item['item_name'],
            'icon_url' => $item['icon_url'],
            'craft_quality' => $item['craft_quality'],
            'profession' => $item['profession'],
            'quantity' => $item['quantity'],
            'value_copper' => $item['current_total_copper'],
            'percent_of_total' => $totalQuantity > 0 ? round(($item['quantity'] / $totalQuantity) * 100, 1) : 0,
        ])->sortByDesc('percent_of_total')->values();

        return response()->json([
            'nodes_per_hour' => $nodesPerHour,
            'avg_gold_per_node' => $avgGoldPerNode,
            'avg_seconds_per_node' => $avgSecondsPerNode,
            'pace_chart' => $paceChart,
            'gold_per_hour_chart' => $goldPerHourChart,
            'item_mix' => $itemMix,
        ]);
    }

    public function summary()
    {
        $sessions = FarmSession::where('status', 'stopped')->get();

        $totalSessions = $sessions->count();
        $totalSeconds = $sessions->sum(fn($s) => $s->started_at->diffInSeconds($s->stopped_at));
        $totalGoldCopper = 0;
        $totalNodes = 0;

        foreach ($sessions as $session) {
            $value = $this->valueService->calculateSessionValue($session);
            $totalGoldCopper += $value['current_value_copper'];
            $totalNodes += $value['total_nodes'];
        }

        return response()->json([
            'total_sessions' => $totalSessions,
            'total_seconds' => $totalSeconds,
            'total_gold_copper' => $totalGoldCopper,
            'total_nodes' => $totalNodes,
        ]);
    }

    public function analytics()
    {
        $sessions = FarmSession::where('status', 'stopped')->get();

        $totalNodes = 0;
        $totalSeconds = 0;
        $totalGoldCopper = 0;
        $itemTotals = [];

        foreach ($sessions as $session) {
            $value = $this->valueService->calculateSessionValue($session);
            $totalNodes += $value['total_nodes'];
            $totalGoldCopper += $value['current_value_copper'];
            $totalSeconds += $session->started_at->diffInSeconds($session->stopped_at);

            foreach ($value['items'] as $item) {
                $key = $item['item_id'];

                if (!isset($itemTotals[$key])) {
                    $itemTotals[$key] = [
                        'item_id' => $item['item_id'],
                        'item_name' => $item['item_name'],
                        'icon_url' => $item['icon_url'],
                        'craft_quality' => $item['craft_quality'],
                        'profession' => $item['profession'],
                        'quantity' => 0,
                        'value_copper' => 0,
                    ];
                }

                $itemTotals[$key]['quantity'] += $item['quantity'];
                $itemTotals[$key]['value_copper'] += $item['current_total_copper'] ?? 0;
            }
        }

        $avgNodesPerHour = $totalSeconds > 0 ? round($totalNodes / ($totalSeconds / 3600), 1) : 0;
        $avgGoldPerNode = $totalNodes > 0 ? round(($totalGoldCopper / $totalNodes) / 10000, 2) : 0;

        $totalQuantity = array_sum(array_column($itemTotals, 'quantity'));
        $itemMix = collect($itemTotals)->map(function ($item) use ($totalQuantity) {
            $item['percent_of_total'] = $totalQuantity > 0 ? round(($item['quantity'] / $totalQuantity) * 100, 1) : 0;
            return $item;
        })->sortByDesc('percent_of_total')->values();

        return response()->json([
            'total_sessions' => $sessions->count(),
            'total_nodes' => $totalNodes,
            'total_seconds' => $totalSeconds,
            'total_gold_copper' => $totalGoldCopper,
            'avg_nodes_per_hour' => $avgNodesPerHour,
            'avg_gold_per_node' => $avgGoldPerNode,
            'item_mix' => $itemMix,
        ]);
    }

    public function destroy(int $id)
    {
        FarmSession::where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    protected function formatSession(FarmSession $session): array
    {
        return [
            'id' => $session->id,
            'character_key' => $session->character_key,
            'mode' => $session->mode,
            'status' => $session->status,
            'planned_duration_minutes' => $session->planned_duration_minutes,
            'started_at' => $session->started_at->toIso8601String(),
            'stopped_at' => $session->stopped_at?->toIso8601String(),
        ];
    }
}