<?php

namespace App\Http\Controllers;

use App\Http\Services\WowCraftHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WowCraftHistoryController extends Controller
{
    public function index(Request $request, WowCraftHistoryService $service)
    {
        $characterKey = (string) $request->query('character');
        $realmSlug = (string) $request->query('realm_slug');
        $range = (string) $request->query('range', '7d');

        if (!$characterKey || !$realmSlug) {
            return response()->json(['entries' => [], 'summary' => null]);
        }

        [$from, $to] = $this->resolveRange($range, $request);

        $result = $service->getHistory($characterKey, $from, $to, $realmSlug);

        return response()->json($result);
    }

    protected function resolveRange(string $range, Request $request): array
    {
        $to = now();

        $from = match ($range) {
            'today' => now()->startOfDay(),
            '1d' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            'custom' => Carbon::parse($request->query('from', now()->subDays(7))),
            default => now()->subDays(7),
        };

        if ($range === 'custom' && $request->query('to')) {
            $to = Carbon::parse($request->query('to'));
        }

        return [$from, $to];
    }
}