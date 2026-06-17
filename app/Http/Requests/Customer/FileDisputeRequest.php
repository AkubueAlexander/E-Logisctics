<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class FileDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Enforced inside the Action layer logic via ownership validations
    }

    public function rules(): array
    {
        return [
            'sub_order_id' => ['required', 'integer', 'exists:sub_orders,id'],
            'reason_category' => ['required', 'string', 'in:missing_items,wrong_order,spoiled_food,poor_quality'],
            'customer_notes' => ['required', 'string', 'min:10', 'max:1500'],
        ];
    }
}
