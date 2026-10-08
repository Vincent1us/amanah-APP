<?php

namespace App\Http\Requests\Auth;

class VerifyResetOtpRequest extends ForgotPasswordRequest
{
    public function rules(): array
    {
        return parent::rules() + ['code' => ['required', 'digits:6']];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'code.required' => 'Kode OTP wajib diisi.',
            'code.digits'   => 'Kode OTP harus 6 digit angka.',
        ];
    }
}
