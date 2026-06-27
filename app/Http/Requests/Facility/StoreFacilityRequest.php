<?php

namespace App\Http\Requests\Facility;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:facilities,name'],
            'description' => ['nullable', 'string'],
            'global_stock' => ['required', 'integer', 'min:0'],
            'condition' => ['required', Rule::in(['good', 'damaged', 'maintenance'])],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
