<?php
namespace App\Http\Controllers\Api\Product;

use App\Actions\Product\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductRequest;
use App\Models\Store;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    /**
     * Store a newly created product inside the merchant's catalog.
     */
    public function store(ProductRequest $request, Store $store, SaveProduct $action): JsonResponse
    {
        // Enforce that only authorized store managers can alter this catalog
        Gate::authorize('update', $store);

        $product = $action->execute($store, $request->validated());

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $product
        ], 201);
    }

    /**
     * Update an existing product profile.
     */
    public function update(ProductRequest $request, Store $store, Product $product, SaveProduct $action): JsonResponse
    {
        Gate::authorize('update', $store);

        // Fail-safe check: Ensure product belongs to this exact store
        if ($product->store_id !== $store->id) {
            return response()->json(['message' => 'Product resource mismatch.'], 403);
        }

        $updatedProduct = $action->execute($store, $request->validated(), $product);

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => $updatedProduct
        ], 200);
    }
}
