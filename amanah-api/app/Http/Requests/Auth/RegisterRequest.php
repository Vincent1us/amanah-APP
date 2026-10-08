<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'  => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => Phone::normalize($this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'min:3', 'max:100'],
            'email'          => ['required', 'email:rfc', 'max:191', 'unique:users,email'],
            'phone'          => ['required', 'regex:'.Phone::REGEX, 'unique:users,phone'],
            'password'       => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'terms_accepted' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'Nama lengkap wajib diisi.',
            'name.min'                => 'Nama lengkap minimal 3 karakter.',
            'email.required'          => 'Alamat email wajib diisi.',
            'email.email'             => 'Format email tidak valid.',
            'email.unique'            => 'Email ini sudah terdaftar. Silakan gunakan email lain atau masuk.',
            'phone.required'          => 'Nomor WhatsApp wajib diisi.',
            'phone.regex'             => 'Format nomor WhatsApp tidak valid. Contoh: 812-3456-7890.',
            'phone.unique'            => 'Nomor WhatsApp ini sudah terdaftar di akun aktif.',
            'password.required'       => 'Kata sandi wajib diisi.',
            'password.confirmed'      => 'Konfirmasi kata sandi tidak cocok.',
            'password.min'            => 'Kata sandi minimal 8 karakter.',
            'password.mixed'          => 'Kata sandi harus mengandung huruf besar dan kecil.',
            'password.numbers'        => 'Kata sandi harus mengandung angka (0-9).',
            'password.symbols'        => 'Kata sandi harus mengandung simbol atau karakter khusus (!@#$).',
            'terms_accepted.accepted' => 'Anda harus menyetujui Syarat & Ketentuan serta Kebijakan Privasi.',
        ];
    }
}
