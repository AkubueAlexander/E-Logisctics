<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class InitiateWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_minor_unit' => ['required', 'integer', 'min:100000'], // Minimum ₦1,000 (100,000 Kobo)
            'store_id'          => ['nullable', 'integer', 'exists:stores,id'], // Present if vendor cash-out
        ];
    }
}
