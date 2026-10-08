<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(private AuthTokenService $tokens) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'phone', 'password']));

        return ApiResponse::success([
            'user'  => (new UserResource($user))->resolve(),
            'token' => $this->tokens->issue($user),
        ], 'Pendaftaran berhasil.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $max = config('amanah.login.max_attempts');
        $lockSeconds = config('amanah.login.lockout_minutes') * 60;
        $key = 'login:'.sha1($request->input('email').'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw $this->locked(RateLimiter::availableIn($key));
        }

        $user = User::where('email', $request->input('email'))->first();

        // Hash::make saat user tidak ada -> waktu respons relatif sama (anti timing attack).
        $valid = $user ? Hash::check($request->input('password'), $user->password)
                       : (bool) Hash::make($request->input('password')) && false;

        if (! $valid) {
            RateLimiter::hit($key, $lockSeconds);
            $remaining = RateLimiter::remaining($key, $max);

            if ($remaining <= 0) {
                throw $this->locked($lockSeconds);
            }
            throw new ApiException(
                'Kombinasi email atau kata sandi tidak cocok. Silakan periksa kembali atau reset sandi Anda.',
                'INVALID_CREDENTIALS', 401, ['remaining_attempts' => $remaining]
            );
        }

        RateLimiter::clear($key);

        return ApiResponse::success([
            'user'  => (new UserResource($user))->resolve(),
            'token' => $this->tokens->issue($user, (bool) $request->boolean('remember_me')),
        ], 'Login berhasil.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(['user' => (new UserResource($request->user()))->resolve()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
        return ApiResponse::success(null, 'Logout berhasil.');
    }

    private function locked(int $seconds): ApiException
    {
        $minutes = (int) ceil($seconds / 60);
        return new ApiException(
            "Terlalu banyak percobaan masuk. Akses dibekukan sementara selama {$minutes} menit demi keamanan akun Anda.",
            'LOGIN_LOCKED', 429, ['retry_after' => $seconds]
        );
    }
}
