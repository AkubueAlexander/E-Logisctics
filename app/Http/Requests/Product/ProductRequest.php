<?php
namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Governed by Controller Gates
    }

    public function rules(): array
    {
        // Get store from route parameter
        $store = $this->route('store');
        $storeId = $store instanceof \App\Models\Store ? $store->id : $store;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_minor_unit' => ['required', 'integer', 'min:100'], // e.g., Minimum 100 kobo / 1 NGN
            'currency_code' => ['code' => 'sometimes', 'string', 'size:3'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],

            // Senior Guard: Ensure the menu category belongs exclusively to THIS store instance
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(function ($query) use ($storeId) {
                    $query->where('store_id', $storeId);
                }),
            ],

            // Validation for flexible product attributes (e.g., sizes, spicy levels)
            'attributes' => ['sometimes', 'array'],
        ];
    }
}
