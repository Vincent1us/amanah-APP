<?php

namespace App\Services;

use App\Models\User;

class AuthTokenService
{
    /** Buat token Sanctum. remember_me => berlaku 30 hari, selain itu 24 jam. */
    public function issue(User $user, bool $remember = false): array
    {
        $ttl = config('amanah.token_ttl');
        $expiresAt = $remember ? now()->addDays($ttl['remember_days']) : now()->addHours($ttl['default_hours']);

        $user->forceFill(['last_login_at' => now()])->save();

        return [
            'access_token' => $user->createToken('mobile', ['*'], $expiresAt)->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_at'   => $expiresAt->toIso8601String(),
        ];
    }
}
