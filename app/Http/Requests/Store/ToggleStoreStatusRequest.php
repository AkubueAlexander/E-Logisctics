<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToggleStoreStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Enforced via Controller Gates
    }

    public function rules(): array
    {
        return [
            'is_active' => [
                'required',
                'boolean'
            ],
        ];
    }
}
