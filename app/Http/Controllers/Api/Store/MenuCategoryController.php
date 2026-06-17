<?php
namespace App\Http\Controllers\Api\Store;

use App\Actions\Store\CreateMenuCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreMenuCategoryRequest;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MenuCategoryController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function store(StoreMenuCategoryRequest $request,Store $store, CreateMenuCategory $action): JsonResponse
    {

        Gate::authorize('create', [Category::class, $store]);


        $category = $action->execute($store,$request->safe()->except('image'), $request->file('image'));

        return response()->json([
            'message' => 'Menu category created.',
            'data' => $category
        ], 201);
    }
}
