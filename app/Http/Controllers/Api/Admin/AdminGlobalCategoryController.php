<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Actions\Admin\CreateGlobalCategory;
use App\Http\Requests\Admin\StoreGlobalCategoryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AdminGlobalCategoryController extends Controller
{


    public function __construct(protected CreateGlobalCategory $createGlobalCategory)
    {

    }

    /**
     * POST Endpoint: Create a system wide global category filter.
     * @throws ValidationException
     */
    public function store(StoreGlobalCategoryRequest $request): JsonResponse
    {
        // Execute creation with validated data payload and file attachment
        $globalCategory = $this->createGlobalCategory->execute(
            $request->validated(),
            $request->file('icon')
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'System global category created successfully.',
            'data'    => $globalCategory
        ], 201);
    }
}
