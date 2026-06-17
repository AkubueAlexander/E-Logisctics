<?php

namespace App\Actions\Store;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateMenuCategory
{
    public function execute(Store $store, array $data ,?UploadedFile $image = null): Category
    {
        $imagePath = null;

        if ($image) {
            $imagePath = $image->store('menu_categories', 'public');
        }

        /** @var Category $category */
        $category = $store->categories()->create([
            'name'       => $data['name'],
            'parent_id'  => $data['parent_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => $data['is_active'] ?? true,
            'image_path' => $imagePath
        ]);

        return $category;
    }
}
