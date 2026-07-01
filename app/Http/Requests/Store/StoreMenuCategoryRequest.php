<?php
namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        // 1. Resolve store context for the rule
        $store = $this->user()->stores()->wherePivot('role', 'manager')->firstOrFail();
        $store = $this->route('store');

        return [
            'name'       => ['required', 'string', 'max:255'],
            'parent_id'  => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('store_id', $store->id) // Secure check
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['nullable', 'boolean'],
            'image'      => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048']
        ];
    }
}
