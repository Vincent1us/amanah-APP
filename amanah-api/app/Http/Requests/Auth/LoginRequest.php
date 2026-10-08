<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'email'       => ['required', 'email:rfc', 'max:191'],
            'password'    => ['required', 'string', 'max:255'],
            'remember_me' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ];
    }
}
