<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class OnboardBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_number' => ['required', 'string', 'digits:10'],
            'bank_code'      => ['required', 'string', 'max:10'],
            'bank_name'      => ['required', 'string', 'max:255'],
            'store_id'       => ['nullable', 'integer', 'exists:stores,id'], // Pass only if a merchant is onboarding
        ];
    }
}
