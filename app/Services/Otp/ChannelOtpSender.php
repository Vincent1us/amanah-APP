<?php

namespace App\Services\Otp;

use App\Contracts\OtpSender;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ChannelOtpSender implements OtpSender
{
    public function send(string $channel, string $destination, string $code, string $purpose): void
    {
        $minutes = config('amanah.otp.expires_minutes');
        $message = "Kode OTP Amanah Anda: {$code}. Berlaku {$minutes} menit. "
            ."JANGAN bagikan kode ini kepada siapa pun, termasuk petugas Amanah.";

        if ($channel === 'email') {
            Mail::raw($message, fn ($m) => $m->to($destination)->subject('Kode Verifikasi Amanah'));
            return;
        }

        $this->sendWhatsapp($destination, $message);
    }

    private function sendWhatsapp(string $phone, string $message): void
    {
        $cfg = config('amanah.otp.whatsapp');

        if ($cfg['driver'] !== 'http') {
            // Driver "log": untuk development. Kode OTP bisa dilihat di storage/logs/laravel.log
            Log::info("[WA-OTP] to={$phone} message={$message}");
            return;
        }

        // Contoh generik untuk gateway WA (mis. Fonnte): header token + form target/message.
        $response = Http::withHeaders([$cfg['auth_header'] => $cfg['token']])
            ->asForm()->timeout(10)
            ->post($cfg['url'], ['target' => ltrim($phone, '+'), 'message' => $message]);

        if ($response->failed()) {
            Log::error('Gagal mengirim OTP WhatsApp', ['status' => $response->status()]);
            throw new ApiException('Gagal mengirim kode OTP via WhatsApp. Silakan coba lagi.', 'OTP_DELIVERY_FAILED', 502);
        }
    }
}
