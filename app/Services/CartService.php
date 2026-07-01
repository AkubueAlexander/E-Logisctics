<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CartService
{
    protected string $prefix = 'customer_cart:';
    protected int $ttl = 86400; // Cart expires after 24 hours of inactivity

    /**
     * Get the raw cart array for a given user.
     */
    public function getCart(int $userId): array
    {
        return Cache::get($this->prefix . $userId, []);
    }

    /**
     * Add or update an item in the cart using its absolute state.
     */
    public function addItem(int $userId, int $productId, int $quantity, array $customizations = []): array
    {
        $cart = $this->getCart($userId);

        if (!empty($customizations)) {
            sort($customizations); // Forces predictable sequential order
        }

        // 1. Generate the unique key first to safely handle removals or overwrites
        $itemKey = $productId . '_' . md5(json_encode($customizations));

        // 2. If the frontend sends 0 or less, it means the item was subtracted down to 0
        if ($quantity <= 0) {
            return $this->removeItem($userId, $itemKey);
        }

        // Fetch product to guarantee existence and capture store context
        $product = Product::findOrFail($productId);

        // 3. Absolute Overwrite: Trust the debounced state sent by the frontend
        $cart[$itemKey] = [
            'product_id' => $product->id,
            'store_id' => $product->store_id,
            'quantity' => $quantity,
            'customizations' => $customizations,
        ];

        Cache::put($this->prefix . $userId, $cart, $this->ttl);

        return $this->getSummary($userId);
    }

    /**
     * Overwrites the cached cart entirely with an array compiled from local storage.
     */
    public function sync(int $userId, array $items): array
    {
        if (empty($items)) {
            $this->clear($userId);
            return $this->getSummary($userId);
        }

        $syncedCart = [];

        // Eagerly pull all products to validate items sent from frontend bulk sync
        $productIds = collect($items)->pluck('product_id')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? 0;
            $customizations = $item['customizations'] ?? [];

            // Skip invalid states or items dropping to zero
            if (!$productId || $quantity <= 0) {
                continue;
            }

            $product = $products->get($productId);
            if (!$product) {
                continue;
            }

            if (!empty($customizations)) {
                sort($customizations); // Forces predictable sequential order
            }

            $itemKey = $productId . '_' . md5(json_encode($customizations));

            $syncedCart[$itemKey] = [
                'product_id' => $product->id,
                'store_id' => $product->store_id,
                'quantity' => $quantity,
                'customizations' => $customizations,
            ];
        }

        Cache::put($this->prefix . $userId, $syncedCart, $this->ttl);

        return $this->getSummary($userId);
    }

    /**
     * Remove a single item variation from the cart.
     */
    public function removeItem(int $userId, string $itemKey): array
    {
        $cart = $this->getCart($userId);

        if (isset($cart[$itemKey])) {
            unset($cart[$itemKey]);
            Cache::put($this->prefix . $userId, $cart, $this->ttl);
        }

        return $this->getSummary($userId);
    }

    /**
     * Clear the entire cart after a successful checkout transaction.
     */
    public function clear(int $userId): void
    {
        Cache::forget($this->prefix . $userId);
    }

    /**
     * Core Architectural Piece: Compiles the raw cart into a multi-store grouped summary.
     */
    public function getSummary(int $userId): array
    {
        $cart = $this->getCart($userId);
        if (empty($cart)) {
            return ['stores' => [], 'grand_total_minor_unit' => 0];
        }

        // 1. Eager-load products to eliminate N+1 queries during loop processing
        $productIds = collect($cart)->pluck('product_id')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->with('store')->get()->keyBy('id');

        // 2. Gather all unique customization option IDs across the entire cart
        $allOptionIds = [];
        foreach ($cart as $item) {
            if (!empty($item['customizations']) && is_array($item['customizations'])) {
                foreach ($item['customizations'] as $optionId) {
                    if (is_numeric($optionId)) {
                        $allOptionIds[] = $optionId;
                    }
                }
            }
        }
        $allOptionIds = array_unique($allOptionIds);

        // 3. Perform a single batch query to look up option details and verify prices securely
        $modifierOptions = [];
        if (!empty($allOptionIds)) {
            $modifierOptions = DB::table('modifier_options')
                ->whereIn('id', $allOptionIds)
                ->where('is_available', true)
                ->get()
                ->keyBy('id');
        }

        $groupedSummary = [];
        $grandTotal = 0;

        // 4. Compile the summary
        foreach ($cart as $itemKey => $item) {
            $product = $products->get($item['product_id']);
            if (!$product || !$product->is_available) {
                continue; // Automatically filter out items removed from catalog or disabled
            }

            // Calculate customization prices for this specific item line
            $optionsTotalPerUnit = 0;
            $compiledCustomizations = [];

            if (!empty($item['customizations']) && is_array($item['customizations'])) {
                foreach ($item['customizations'] as $optionId) {
                    $option = $modifierOptions->get($optionId);
                    if ($option) {
                        $optionsTotalPerUnit += $option->price_minor_unit;

                        // Attach clean metadata to the output object for your frontend checkout UI
                        $compiledCustomizations[] = [
                            'id' => $option->id,
                            'name' => $option->name,
                            'price_minor_unit' => $option->price_minor_unit,
                        ];
                    }
                }
            }

            $storeId = $product->store_id;

            // Final math logic: (Base Product Price + Option Addons) * Quantity
            $unitPriceWithModifiers = $product->price_minor_unit + $optionsTotalPerUnit;
            $itemTotal = $unitPriceWithModifiers * $item['quantity'];
            $grandTotal += $itemTotal;

            // Initialize store bucket if not exists
            if (!isset($groupedSummary[$storeId])) {
                $groupedSummary[$storeId] = [
                    'store_id' => $storeId,
                    'store_name' => $product->store->name,
                    'store_subtotal_minor_unit' => 0,
                    'items' => [],
                ];
            }

            $groupedSummary[$storeId]['store_subtotal_minor_unit'] += $itemTotal;
            $groupedSummary[$storeId]['items'][] = [
                'item_key' => $itemKey,
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $item['quantity'],
                'unit_price_minor_unit' => $unitPriceWithModifiers,
                'total_price_minor_unit' => $itemTotal,
                'customizations' => $compiledCustomizations, // Now returns full objects to the frontend
            ];
        }

        return [
            'stores' => array_values($groupedSummary), // Flatten keys for predictable JSON response arrays
            'grand_total_minor_unit' => $grandTotal,
        ];
    }
}
