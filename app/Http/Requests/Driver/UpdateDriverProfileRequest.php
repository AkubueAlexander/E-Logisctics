<?php
namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vehicle_type' => ['sometimes', 'string', 'in:bicycle,motorcycle,car'],
            'license_plate' => ['nullable', 'string', 'max:20'],
        ];
    }
}
