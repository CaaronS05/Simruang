<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'campus_id' => ['required', 'string', 'max:255', 'unique:users,campus_id'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $domain = str($this->string('email'))->after('@')->value();

                if (! in_array($domain, ['student.petra.ac.id', 'petra.ac.id'], true)) {
                    $validator->errors()->add('email', 'Email harus menggunakan domain @student.petra.ac.id atau @petra.ac.id.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => str($this->string('email'))->lower()->value(),
        ]);
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar.',
            'campus_id.unique' => 'ID kampus sudah terdaftar.',
        ];
    }
}
