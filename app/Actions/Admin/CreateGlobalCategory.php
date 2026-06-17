<?php

namespace App\Actions\Admin;

use App\Models\GlobalCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateGlobalCategory
{

    /**
     * @throws ValidationException
     */
    public function execute(array $data, ?UploadedFile $icon = null): GlobalCategory
    {

        $iconUrl = null;

        if ($icon) {
            $path = $icon->store('categories/icons', 'public');
            $iconUrl = Storage::url($path);
        }

        //Create and return the category
        return GlobalCategory::create([
            'name'      => $data['name'],
            'icon_url'  => $iconUrl,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
