<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreGlobalCategoryRequest extends FormRequest
{


    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:100', 'unique:global_categories,name'],
            'is_active' => ['nullable', 'boolean'],
            'icon'      => ['nullable', 'image', 'mimes:jpeg,png,jpg,svg', 'max:2048'], // 2MB Max for icons
        ];
    }
}
