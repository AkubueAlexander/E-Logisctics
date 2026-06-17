<?php

namespace App\Http\Requests\Store;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteRepresentativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users,email'],

            'system_role' => ['required', Rule::enum(UserRole::class)],

            'store_role'  => ['required', 'string', 'in:manager,staff'],
        ];
    }
}
