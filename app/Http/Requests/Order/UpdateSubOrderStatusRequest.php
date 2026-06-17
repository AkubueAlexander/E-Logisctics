<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Enforced via Controller Gates matching store permissions
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(['accepted', 'preparing', 'ready_for_pickup', 'cancelled'])
            ],
            // Prep time is strictly mandatory only when transitioning to the accepted/preparing stage
            'estimated_prep_time_minutes' => [
                Rule::requiredIf(in_array($this->input('status'), ['accepted', 'preparing'])),
                'nullable',
                'integer',
                'min:1',
                'max:240'
            ],
        ];
    }
}
