<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class WhatsappOtpRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::normalize($this->input('phone'))]);
    }

    public function rules(): array
    {
        return ['phone' => ['required', 'regex:'.Phone::REGEX]];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Nomor WhatsApp wajib diisi.',
            'phone.regex'    => 'Format nomor WhatsApp tidak valid. Contoh: 812-3456-7890.',
        ];
    }
}
