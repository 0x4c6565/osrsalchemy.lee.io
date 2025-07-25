<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AlchemyController extends Controller
{
    /**
     * Display a listing of items with current GE prices.
     */
    public function index(Request $request)
    {
        // Load item metadata from storage/app/items.json
        $items = $this->fetchItemMappingWithCache($request);
        $itemPrices = $this->fetchItemPricesWithCache($request);

        // Enrich each item with price data
        $itemsWithPrices = collect($items)->map(function ($item) use ($itemPrices) {
            $price = $itemPrices['data'][$item['id']] ?? null;
            return array_merge($item, ['price' => $price]);
        })->all();


        $naturePrice = collect($itemsWithPrices)->firstWhere('id', 561) ?? null;

        return view('index', [
            'natureItem' => $naturePrice,
            'items' => $itemsWithPrices,
        ]);
    }

    // /**
    //  * Load items.json from local storage.
    //  *
    //  * @return array<int, array<string,mixed>>
    //  */
    // protected function loadItemsFromStorage(): array
    // {
    //     $path = 'items.json'; // storage/app/items.json
    //     if (!Storage::disk('local')->exists($path)) {
    //         return [];
    //     }

    //     $raw = Storage::disk('local')->get($path);
    //     $decoded = json_decode($raw, true);

    //     if (!is_array($decoded)) {
    //         return [];
    //     }

    //     // Filter out entries without an ID
    //     return array_values(array_filter($decoded, function ($row) {
    //         return isset($row['id']) && is_numeric($row['id']);
    //     }));
    // }

    // protected function fetchPriceForItem(Request $request, int $id): ?array
    // {
    //     if ($id <= 0) {
    //         return null;
    //     }

    //     $itemPrices = $this->fetchItemPricesWithCache($request);
    //     if (!$itemPrices || !isset($itemPrices['data'][$id])) {
    //         return null;
    //     }

    //     return $itemPrices['data'][$id];
    // }

    protected function fetchItemMappingWithCache(Request $request): ?array
    {
        $cacheKey = 'osrs_item_mapping';

        if ($request->has("refresh") && Cache::has($cacheKey)) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addDays(1), function () {
            return $this->fetchItemMapping();
        });
    }

    protected function fetchItemMapping(): ?array
    {
        $url = 'https://prices.runescape.wiki/api/v1/osrs/mapping';

        try {
            Log::info("Fetching item mapping from: $url");
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
    }

    protected function fetchItemPricesWithCache(Request $request): ?array
    {
        $cacheKey = 'osrs_item_prices';

        if ($request->has("refresh") && Cache::has($cacheKey)) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addSeconds(60), function () {
            return $this->fetchItemPrices();
        });
    }

    protected function fetchItemPrices(): ?array
    {
        $url = 'https://prices.runescape.wiki/api/v1/osrs/latest';

        try {
            Log::info("Fetching item prices from: $url");
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
    }
}
