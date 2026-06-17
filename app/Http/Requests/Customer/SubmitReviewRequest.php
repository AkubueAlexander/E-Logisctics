<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class SubmitReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Enforced via Controller authorization guards
    }

    public function rules(): array
    {
        return [
            // Driver specific review properties
            'driver_rating' => ['required_with:driver_comment', 'nullable', 'integer', 'between:1,5'],
            'driver_comment' => ['nullable', 'string', 'max:1000'],

            // Nested store reviews block array
            'store_reviews' => ['required', 'array', 'min:1'],
            'store_reviews.*.sub_order_id' => ['required', 'integer', 'exists:sub_orders,id'],
            'store_reviews.*.rating' => ['required', 'integer', 'between:1,5'],
            'store_reviews.*.comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
