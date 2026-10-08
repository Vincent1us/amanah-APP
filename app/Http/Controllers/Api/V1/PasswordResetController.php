<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\OtpCode;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\OtpService;
use App\Support\ApiResponse;
use App\Support\Mask;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(private OtpService $otp, private AuthTokenService $tokens) {}

    /** Langkah 1: kirim OTP ke email / WhatsApp. */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $data = $this->otp->request(
            OtpCode::PURPOSE_RESET, $request->input('method'), $request->destination(), $request->ip()
        );

        return ApiResponse::success($data, 'Jika akun terdaftar, kode verifikasi telah dikirim.');
    }

    /** Langkah 2: verifikasi OTP => dapat reset_token (sekali pakai, berlaku singkat). */
    public function verifyOtp(VerifyResetOtpRequest $request): JsonResponse
    {
        $method = $request->input('method');
        $otp = $this->otp->verify(OtpCode::PURPOSE_RESET, $request->destination(), $request->input('code'));

        $user = User::find($otp->user_id);
        if (! $user) {
            throw new ApiException('Akun tidak ditemukan.', 'USER_NOT_FOUND', 404);
        }

        $column = $method === 'email' ? 'email_verified_at' : 'phone_verified_at';
        if (! $user->{$column}) {
            $user->forceFill([$column => now()])->save();
        }

        PasswordReset::where('user_id', $user->id)->whereNull('used_at')->delete();

        $plain = Str::random(64);
        $reset = PasswordReset::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes(config('amanah.reset_token_minutes')),
        ]);

        return ApiResponse::success([
            'reset_token' => $plain,
            'expires_at'  => $reset->expires_at->toIso8601String(),
            'user'        => [
                'name'         => $user->name,
                'email_masked' => Mask::email($user->email),
                'verified'     => true,
            ],
        ], 'Verifikasi berhasil. Silakan atur kata sandi baru.');
    }

    /** Langkah 3: set password baru + langsung login. */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $record = PasswordReset::where('token_hash', hash('sha256', $request->input('reset_token')))
            ->whereNull('used_at')->where('expires_at', '>', now())->first();

        if (! $record) {
            throw new ApiException('Token reset tidak valid atau sudah kedaluwarsa. Silakan ulangi proses lupa kata sandi.', 'RESET_TOKEN_INVALID', 422);
        }

        $user = $record->user;

        if (Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Kata sandi baru harus berbeda dari kata sandi yang pernah digunakan sebelumnya.'],
            ]);
        }

        DB::transaction(function () use ($record, $user, $request) {
            $claimed = PasswordReset::whereKey($record->id)->whereNull('used_at')->update(['used_at' => now()]);
            if (! $claimed) {
                throw new ApiException('Token reset tidak valid atau sudah kedaluwarsa.', 'RESET_TOKEN_INVALID', 422);
            }
            $user->forceFill(['password' => $request->input('password')])->save();
            $user->tokens()->delete(); // paksa logout semua perangkat lama
        });

        return ApiResponse::success([
            'user'  => (new UserResource($user))->resolve(),
            'token' => $this->tokens->issue($user),
        ], 'Kata sandi berhasil diubah.');
    }
}
