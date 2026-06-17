<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Governed by auth:sanctum route middleware
    }

    public function rules(): array
    {
        return [
            'delivery_address' => ['required', 'string', 'max:500'],
            'latitude'          => ['required', 'numeric', 'between:-90,90'],
            'longitude'         => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
