<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

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
     * Add or update an item in the cart.
     */
    public function addItem(int $userId, int $productId, int $quantity, array $customizations = []): array
    {
        $cart = $this->getCart($userId);

        // Fetch product to guarantee existence and capture store context
        $product = Product::findOrFail($productId);

        if ($quantity <= 0) {
            return $this->removeItem($userId, $productId);
        }

        // Unique key combining product and customizations to track distinct line variations
        $itemKey = $productId . '_' . md5(json_encode($customizations));

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

        // Eager-load products to eliminate N+1 queries during loop processing
        $productIds = collect($cart)->pluck('product_id')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->with('store')->get()->keyBy('id');

        $groupedSummary = [];
        $grandTotal = 0;

        foreach ($cart as $itemKey => $item) {
            $product = $products->get($item['product_id']);
            if (!$product || !$product->is_available) {
                continue; // Automatically filter out items removed from catalog or disabled
            }

            $storeId = $product->store_id;
            $itemTotal = $product->price_minor_unit * $item['quantity'];
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
                'unit_price_minor_unit' => $product->price_minor_unit,
                'total_price_minor_unit' => $itemTotal,
                'customizations' => $item['customizations'],
            ];
        }

        return [
            'stores' => array_values($groupedSummary), // Flatten keys for predictable JSON response arrays
            'grand_total_minor_unit' => $grandTotal,
        ];
    }
}
