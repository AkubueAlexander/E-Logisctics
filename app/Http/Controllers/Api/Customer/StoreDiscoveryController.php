<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StoreDiscoveryController extends Controller
{
    /**
     * List all operational stores sorted by PostGIS spatial proximity.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'numeric', 'max:20000'], // Default max 20km
        ]);

        $lat = $validated['latitude'];
        $lng = $validated['longitude'];
        $radius = $validated['radius_meters'] ?? 5000; // Default to 5 kilometers

        // High Performance Spatial Query via PostGIS
        // Filters out inactive shops instantly before running calculations
        $stores = Store::query()
            ->where('is_active', true)
            ->select([
                'id',
                'name',
                'slug',
                'address',
                'latitude',
                'longitude',
                DB::raw("ST_DistanceSphere(
                    ST_GeomFromText('POINT(' || longitude || ' ' || latitude || ')'),
                    ST_GeomFromText('POINT({$lng} {$lat})')
                ) AS distance_meters")
            ])
            ->having('distance_meters', '<=', $radius)
            ->orderBy('distance_meters', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'customer_coordinates' => ['latitude' => $lat, 'longitude' => $lng],
            'search_radius_meters' => $radius,
            'count' => $stores->count(),
            'data' => $stores
        ]);
    }

    /**
     * Retrieve a detailed storefront layout and product menu.
     * This is where the cache key from ToggleStoreStatus is generated and populated!
     */
    public function show(Store $store): JsonResponse
    {
        // Tech Lead Guard Clause: Do not allow browsing menus of inactive stores
        if (!$store->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'This storefront is currently closed or offline.'
            ], 404);
        }

        $cacheKey = "store_profile:{$store->id}";

        // Cache TTL (Time To Live): 24 hours.
        // It remains pristine in memory until evicted via ToggleStoreStatus or an inventory update!
        $cachedStorefrontData = Cache::remember($cacheKey, now()->addHours(24), function () use ($store) {

            // Eager-load deep nested menu graphs securely: Store -> Custom Categories -> Products
            $store->load([
                'storeCategories' => function ($query) {
                    $query->orderBy('sort_order', 'asc');
                },
                'storeCategories.products' => function ($query) {
                    $query->where('is_available', true)->orderBy('created_at', 'desc');
                }
            ]);

            // Transform structural object to an optimized JSON transfer array
            return [
                'store_id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'address' => $store->address,
                'menu_structure' => $store->storeCategories->map(fn ($category) => [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'products' => $category->products->map(fn ($product) => [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'description' => $product->description,
                        'price_minor_unit' => $product->price_minor_unit, // Formatted for NGN handling
                        'customization_attributes' => $product->attributes, // PostgreSQL JSONB block
                    ]),
                ]),
                'cached_at' => now()->toIso8601String()
            ];
        });

        return response()->json([
            'status' => 'success',
            'source' => Cache::has($cacheKey) ? 'redis_cache_layer' : 'database_hydration',
            'data' => $cachedStorefrontData
        ]);
    }
}
