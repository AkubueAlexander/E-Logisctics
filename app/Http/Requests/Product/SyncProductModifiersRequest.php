<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class SyncProductModifiersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'groups' => 'present|array',
            'groups.*.id' => 'nullable|integer',
            'groups.*.name' => 'required|string|max:255',
            'groups.*.is_required' => 'required|boolean',
            'groups.*.min_selection' => 'required|integer|min:0',
            'groups.*.max_selection' => 'required|integer|min:1',

            // Nested Options Validation
            'groups.*.options' => 'required|array|min:1',
            'groups.*.options.*.id' => 'nullable|integer',
            'groups.*.options.*.name' => 'required|string|max:255',
            'groups.*.options.*.price_minor_unit' => 'required|integer|min:0',
            'groups.*.options.*.is_available' => 'required|boolean',
        ];
    }
}
