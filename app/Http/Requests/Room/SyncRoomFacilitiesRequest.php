<?php

namespace App\Http\Requests\Room;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRoomFacilitiesRequest extends FormRequest
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
            'facilities' => ['required', 'array'],
            'facilities.*.facility_id' => ['required', 'integer', 'distinct', Rule::exists('facilities', 'id')->where('is_active', true)],
            'facilities.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
