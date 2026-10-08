<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reset_token' => ['required', 'string', 'size:64'],
            'password'    => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function messages(): array
    {
        return [
            'reset_token.required' => 'Token reset wajib diisi.',
            'reset_token.size'     => 'Token reset tidak valid.',
            'password.required'    => 'Kata sandi baru wajib diisi.',
            'password.confirmed'   => 'Konfirmasi kata sandi tidak cocok.',
            'password.min'         => 'Kata sandi minimal 8 karakter.',
            'password.mixed'       => 'Kata sandi harus mengandung huruf besar dan kecil.',
            'password.numbers'     => 'Kata sandi harus mengandung angka (0-9).',
            'password.symbols'     => 'Kata sandi harus mengandung simbol atau karakter khusus (!@#$).',
        ];
    }
}
