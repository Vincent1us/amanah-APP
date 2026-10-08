<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResendOtpRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $dest = (string) $this->input('destination');
        $this->merge(['destination' => $this->input('channel') === 'email' ? strtolower(trim($dest)) : Phone::normalize($dest)]);
    }

    public function rules(): array
    {
        return [
            'purpose'     => ['required', 'in:login,reset_password'],
            'channel'     => ['required', 'in:email,whatsapp', Rule::when($this->input('purpose') === 'login', ['in:whatsapp'])],
            'destination' => ['required', Rule::when($this->input('channel') === 'email', ['email:rfc'], ['regex:'.Phone::REGEX])],
        ];
    }

    public function messages(): array
    {
        return [
            'purpose.in'        => 'Tujuan OTP harus login atau reset_password.',
            'channel.in'        => 'Kanal OTP tidak valid untuk tujuan ini.',
            'destination.email' => 'Format email tidak valid.',
            'destination.regex' => 'Format nomor WhatsApp tidak valid.',
        ];
    }
}
