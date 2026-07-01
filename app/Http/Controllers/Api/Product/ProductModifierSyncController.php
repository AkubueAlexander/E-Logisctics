<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\SyncProductModifiersRequest;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductModifierSyncController extends Controller
{
    /**
     * Atomically sync all modifier groups and options for a specific product.
     */
    public function store(SyncProductModifiersRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();
        $groupsPayload = $validated['groups'] ?? [];

        DB::transaction(function () use ($product, $groupsPayload) {
            $keepGroupIds = [];

            /** @var ModifierGroup $group */

            foreach ($groupsPayload as $groupData) {
                // 1. Upsert Modifier Group scoped directly to this product
                $group = $product->modifierGroups()->updateOrCreate(
                    ['id' => $groupData['id'] ?? null],
                    [
                        'name' => $groupData['name'],
                        'is_required' => $groupData['is_required'],
                        'min_selection' => $groupData['min_selection'],
                        'max_selection' => $groupData['max_selection'],
                    ]
                );

                $keepGroupIds[] = $group->id;
                $keepOptionIds = [];

                // 2. Upsert Options scoped directly to this verified group
                foreach ($groupData['options'] as $optionData) {
                    $option = $group->options()->updateOrCreate(
                        ['id' => $optionData['id'] ?? null],
                        [
                            'name' => $optionData['name'],
                            'price_minor_unit' => $optionData['price_minor_unit'],
                            'is_available' => $optionData['is_available'],
                        ]
                    );

                    $keepOptionIds[] = $option->id;
                }


                // 3. Prune options removed by admin for this group
                $group->options()->whereNotIn('id', $keepOptionIds)->update(['is_available' => false]);
            }

            ModifierOption::whereHas('modifierGroup', function ($query) use ($product, $keepGroupIds) {
                $query->where('product_id', $product->id)
                    ->whereNotIn('id', $keepGroupIds);
            })
                ->update(['is_available' => false]);
        });

        // 5. Eager-load the newly updated structure to return a crisp response payload
        $product->load('modifierGroups.options');

        return response()->json([
            'message' => 'Product modifiers synchronized successfully.',
            'data' => $product->modifierGroups
        ], 200);
    }
}
