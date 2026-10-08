<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
        if ($this->filled('phone')) {
            $this->merge(['phone' => Phone::normalize($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'in:email,whatsapp'],
            'email'  => ['required_if:method,email', 'nullable', 'email:rfc', 'max:191'],
            'phone'  => ['required_if:method,whatsapp', 'nullable', 'regex:'.Phone::REGEX],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required'    => 'Pilih metode verifikasi (email atau whatsapp).',
            'method.in'          => 'Metode verifikasi harus email atau whatsapp.',
            'email.required_if'  => 'Alamat email wajib diisi.',
            'email.email'        => 'Format email tidak valid.',
            'phone.required_if'  => 'Nomor WhatsApp wajib diisi.',
            'phone.regex'        => 'Format nomor WhatsApp tidak valid. Contoh: 812-3456-7890.',
        ];
    }

    /** Alamat tujuan OTP sesuai metode yang dipilih. */
    public function destination(): string
    {
        return $this->input('method') === 'email' ? $this->input('email') : $this->input('phone');
    }
}
