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
    public function index(Request $request)
    {
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

        Log::info("Fetching item mapping from: $url");
        $response = Http::acceptJson()->withUserAgent('OSRSPriceCheck')->timeout(10)->get($url);

        if (!$response->ok()) {
            Log::error("Failed to fetch item mapping: " . $response->status() . ' - ' . $response->body());
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
        $url = 'https://prices.runescape.wiki/api/v1/osrs/5m';

        Log::info("Fetching item prices from: $url");
        $response = Http::acceptJson()->withUserAgent('OSRSPriceCheck')->timeout(10)->get($url);

        if (!$response->ok()) {
            Log::error("Failed to fetch item prices: " . $response->status() . ' - ' . $response->body());
            return null;
        }

        return $response->json();
    }
}
