<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\WhatsappOtpRequest;
use App\Http\Requests\Auth\WhatsappVerifyRequest;
use App\Http\Resources\UserResource;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class WhatsappLoginController extends Controller
{
    public function __construct(private OtpService $otp, private AuthTokenService $tokens) {}

    public function requestOtp(WhatsappOtpRequest $request): JsonResponse
    {
        $data = $this->otp->request(OtpCode::PURPOSE_LOGIN, 'whatsapp', $request->input('phone'), $request->ip());

        return ApiResponse::success($data, 'Jika nomor terdaftar, kode OTP telah dikirim ke WhatsApp Anda.');
    }

    public function verifyOtp(WhatsappVerifyRequest $request): JsonResponse
    {
        $phone = $request->input('phone');
        $otp = $this->otp->verify(OtpCode::PURPOSE_LOGIN, $phone, $request->input('code'));

        $user = User::find($otp->user_id);
        if (! $user) {
            throw new ApiException('Akun tidak ditemukan.', 'USER_NOT_FOUND', 404);
        }

        if (! $user->phone_verified_at) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        return ApiResponse::success([
            'user'  => (new UserResource($user))->resolve(),
            'token' => $this->tokens->issue($user, (bool) $request->boolean('remember_me')),
        ], 'Login berhasil.');
    }
}
