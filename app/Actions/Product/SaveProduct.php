<?php
namespace App\Actions\Product;

use App\Models\Store;
use App\Models\Product;

class SaveProduct
{
    /**
     * Execute the product creation or modification logic.
     */
    public function execute(Store $store, array $data, ?Product $product = null): Product
    {
        if (!$product) {
            $product = new Product();
            $product->store_id = $store->id;
        }

        $product->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_minor_unit' => $data['price_minor_unit'],
            'category_id' => $data['category_id'],
            'stock_quantity' => $data['stock_quantity'] ?? 0,
            'is_available' => $data['is_available'] ?? true,
            'attributes' => $data['attributes'] ?? [],
            'currency_code' => $data['currency_code'] ?? 'NGN',
        ]);

        $product->save();

        return $product;
    }
}
