<?php
namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class SaveStoreProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Make sure to set this to true if you handle auth elsewhere
    }

    /**
     * Intercept and decode JSON strings sent via form-data before validation.
     */
    protected function prepareForValidation(): void
    {
        $mergeData = [];

        // Decode operating_hours if it was sent as a JSON string
        if ($this->has('operating_hours') && is_string($this->operating_hours)) {
            $mergeData['operating_hours'] = json_decode($this->operating_hours, true);
        }

        // Decode global_categories if it was sent as a JSON string
        if ($this->has('global_categories') && is_string($this->global_categories)) {
            $mergeData['global_categories'] = json_decode($this->global_categories, true);
        }

        // If we decoded anything, merge the arrays back into the request body
        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'operating_hours' => ['nullable', 'array'],
            'global_categories' => ['nullable', 'array'],
            'global_categories.*' => ['integer', 'exists:global_categories,id'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
