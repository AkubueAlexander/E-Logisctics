<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateCartRequest;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Retrieve the current structured customer cart.
     */
    public function index(Request $request): JsonResponse
    {
        $summary = $this->cartService->getSummary($request->user()->id);
        return response()->json(['data' => $summary], 200);
    }

    /**
     * Add or update an item inside the customer cart (Absolute Quantity).
     */
    public function store(UpdateCartRequest $request): JsonResponse
    {
        $summary = $this->cartService->addItem(
            $request->user()->id,
            $request->input('product_id'),
            $request->input('quantity'),
            $request->input('customizations', [])
        );

        return response()->json([
            'message' => 'Cart updated successfully.',
            'data' => $summary
        ], 200);
    }

    /**
     * Bulk sync the entire frontend local storage cart with the Redis cache.
     * Expects an array of items: [['product_id' => x, 'quantity' => y, 'customizations' => []], ...]
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'present|array',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:0',
            'items.*.customizations' => 'nullable|array',
        ]);

        $summary = $this->cartService->sync(
            $request->user()->id,
            $request->input('items', [])
        );

        return response()->json([
            'message' => 'Cart synchronized successfully.',
            'data' => $summary
        ], 200);
    }

    /**
     * Remove a distinct line item variant entirely from the cart.
     */
    public function destroy(Request $request, string $itemKey): JsonResponse
    {
        $summary = $this->cartService->removeItem($request->user()->id, $itemKey);

        return response()->json([
            'message' => 'Item removed from cart.',
            'data' => $summary
        ], 200);
    }
}
