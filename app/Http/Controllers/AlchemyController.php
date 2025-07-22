<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AlchemyController extends Controller
{
    /**
     * Display a listing of items with current GE prices.
     */
    public function index(Request $request)
    {
        // Load item metadata from storage/app/items.json
        $items = $this->loadItemsFromStorage();

        $naturePrice = $this->fetchPriceForItem($request, 561);

        // Enrich each item with price data
        $itemsWithPrices = collect($items)->map(function ($item) use ($request) {
            $price = $this->fetchPriceForItem($request, (int)($item['id'] ?? 0));
            return array_merge($item, ['price' => $price]);
        })->all();

        return view('index', [
            'naturePrice' => $naturePrice,
            'items' => $itemsWithPrices,
        ]);
    }

    /**
     * Load items.json from local storage.
     *
     * @return array<int, array<string,mixed>>
     */
    protected function loadItemsFromStorage(): array
    {
        $path = 'items.json'; // storage/app/items.json
        if (!Storage::disk('local')->exists($path)) {
            return [];
        }

        $raw = Storage::disk('local')->get($path);
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        // Filter out entries without an ID
        return array_values(array_filter($decoded, function ($row) {
            return isset($row['id']) && is_numeric($row['id']);
        }));
    }

    /**
     * Fetch price data for an item ID from the RuneScape prices API.
     *
     * Response shape (example):
     * {
     *   "data": {
     *     "1395": {"high":8990,"highTime":1753101094,"low":8990,"lowTime":1753101095}
     *   }
     * }
     *
     * @param  int  $id
     * @return array<string,mixed>|null
     */
    protected function fetchPriceForItem(Request $request, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $cacheKey = 'osrs_price_' . $id;

        $itemPrices = $this->fetchItemPrices($request, $cacheKey);
        if (!$itemPrices || !isset($itemPrices['data'][$id])) {
            return null;
        }

        return $itemPrices['data'][$id];
    }

    protected function fetchItemPrices(Request $request, $cacheKey): ?array
    {
        return Cache::remember($cacheKey, now()->addSeconds(60), function () {
            $url = 'https://prices.runescape.wiki/api/v1/osrs/latest';

            try {
                $response = Http::acceptJson()->withUserAgent('OSRSPriceCheck')->timeout(10)->get($url);
            } catch (\Throwable $e) {
                // Network error
                return null;
            }

            if (!$response->ok()) {
                dd($response->status(), $response->body());
                return null;
            }

            return $response->json();
        });
    }
}
