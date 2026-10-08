<?php

namespace App\Services;

use App\Contracts\OtpSender;
use App\Exceptions\ApiException;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Mask;

class OtpService
{
    public function __construct(private OtpSender $sender) {}

    /**
     * Minta OTP. Respons selalu sama baik user terdaftar maupun tidak
     * (mencegah user enumeration); OTP hanya benar-benar dikirim jika user ada.
     */
    public function request(string $purpose, string $channel, string $destination, ?string $ip = null): array
    {
        $user = User::where($channel === 'email' ? 'email' : 'phone', $destination)->first();

        $payload = [
            'channel'            => $channel,
            'masked_destination' => Mask::destination($channel, $destination),
            'expires_in'         => config('amanah.otp.expires_minutes') * 60,
            'resend_in'          => config('amanah.otp.resend_cooldown_seconds'),
        ];

        if (! $user) {
            return $payload;
        }

        $code = $this->issue($user, $purpose, $channel, $destination, $ip);

        if (config('amanah.otp.expose_in_response')) {
            $payload['debug_otp'] = $code; // hanya development!
        }

        return $payload;
    }

    private function issue(User $user, string $purpose, string $channel, string $destination, ?string $ip): string
    {
        // Cooldown kirim ulang (juga dipakai sebagai masa blokir setelah gagal berkali-kali).
        $latest = OtpCode::where('destination', $destination)->where('purpose', $purpose)
            ->whereNull('verified_at')->latest('id')->first();

        if ($latest && $latest->resend_available_at && $latest->resend_available_at->isFuture()) {
            $wait = (int) now()->diffInSeconds($latest->resend_available_at, true);
            throw new ApiException(
                "Mohon tunggu {$wait} detik sebelum meminta kode baru.",
                'OTP_RESEND_TOO_SOON', 429, ['retry_after' => $wait]
            );
        }

        // Matikan semua OTP lama yang belum dipakai: hanya OTP terbaru yang berlaku.
        OtpCode::where('destination', $destination)->where('purpose', $purpose)
            ->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'user_id'             => $user->id,
            'channel'             => $channel,
            'destination'         => $destination,
            'purpose'             => $purpose,
            'code_hash'           => $this->hash($code, $destination, $purpose),
            'expires_at'          => now()->addMinutes(config('amanah.otp.expires_minutes')),
            'resend_available_at' => now()->addSeconds(config('amanah.otp.resend_cooldown_seconds')),
            'ip_address'          => $ip,
        ]);

        $this->sender->send($channel, $destination, $code, $purpose);

        return $code;
    }

    /** Validasi OTP. Sukses => OTP langsung "consumed" (sekali pakai). */
    public function verify(string $purpose, string $destination, string $code): OtpCode
    {
        $max = config('amanah.otp.max_attempts');

        $otp = OtpCode::where('destination', $destination)->where('purpose', $purpose)
            ->whereNull('consumed_at')->latest('id')->first();

        if (! $otp) {
            throw new ApiException('Kode OTP tidak ditemukan atau sudah digunakan. Silakan minta kode baru.', 'OTP_NOT_FOUND', 422);
        }

        if ($otp->expires_at->isPast()) {
            throw new ApiException('Kode OTP sudah kedaluwarsa. Silakan minta kode baru.', 'OTP_EXPIRED', 422);
        }

        if ($otp->attempts >= $max) {
            throw $this->exceeded($otp);
        }

        $otp->increment('attempts'); // atomik di level DB

        if (! hash_equals($otp->code_hash, $this->hash($code, $destination, $purpose))) {
            $remaining = $max - $otp->attempts;
            if ($remaining <= 0) {
                throw $this->exceeded($otp);
            }
            throw new ApiException(
                "Kode OTP tidak sesuai. Sisa percobaan: {$remaining} kali.",
                'OTP_INVALID', 422, ['remaining_attempts' => $remaining]
            );
        }

        // Klaim atomik: jika 2 request benar bersamaan, hanya satu yang berhasil.
        $claimed = OtpCode::whereKey($otp->id)->whereNull('consumed_at')
            ->update(['verified_at' => now(), 'consumed_at' => now()]);

        if (! $claimed) {
            throw new ApiException('Kode OTP tidak ditemukan atau sudah digunakan. Silakan minta kode baru.', 'OTP_NOT_FOUND', 422);
        }

        return $otp;
    }

    private function exceeded(OtpCode $otp): ApiException
    {
        $minutes = config('amanah.otp.lockout_minutes');
        // Blokir permintaan OTP baru selama masa lockout.
        $otp->update(['resend_available_at' => now()->addMinutes($minutes)]);

        return new ApiException(
            "Terlalu banyak percobaan salah. Verifikasi dibekukan sementara selama {$minutes} menit demi keamanan akun Anda.",
            'OTP_ATTEMPTS_EXCEEDED', 429, ['retry_after' => $minutes * 60]
        );
    }

    private function hash(string $code, string $destination, string $purpose): string
    {
        return hash_hmac('sha256', "{$code}|{$destination}|{$purpose}", (string) config('app.key'));
    }
}
