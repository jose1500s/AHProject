<?php

namespace App\Http\Controllers;

use App\Http\Services\WowCraftHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WowCraftHistoryController extends Controller
{
    private const LOCAL_TIMEZONE = 'America/Mexico_City';

    public function index(Request $request, WowCraftHistoryService $service)
    {
        $realmSlug = (string) $request->query('realm_slug');
        $range = (string) $request->query('range', '7d');

        if (!$realmSlug) {
            return response()->json(['entries' => [], 'summary' => null]);
        }

        [$from, $to] = $this->resolveRange($range, $request);

        $result = $service->getHistoryAll($from, $to, $realmSlug);

        return response()->json($result);
    }

    protected function resolveRange(string $range, Request $request): array
    {
        $to = now();

        $from = match ($range) {
            'today' => Carbon::now(self::LOCAL_TIMEZONE)->startOfDay()->utc(),
            '1d' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            'custom' => Carbon::parse($request->query('from', now()->subDays(7)), self::LOCAL_TIMEZONE)->utc(),
            default => now()->subDays(7),
        };

        if ($range === 'custom' && $request->query('to')) {
            $to = Carbon::parse($request->query('to'), self::LOCAL_TIMEZONE)->utc();
        }

        return [$from, $to];
    }

    public function destroy(int $id)
    {
        \App\Models\WowCraftHistory::where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }
}