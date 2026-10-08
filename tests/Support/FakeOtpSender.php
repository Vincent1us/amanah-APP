<?php

namespace Tests\Support;

use App\Contracts\OtpSender;

/** Pengganti pengirim OTP di test: menyimpan kode di memori, tidak mengirim apa pun. */
class FakeOtpSender implements OtpSender
{
    public array $sent = [];

    public function send(string $channel, string $destination, string $code, string $purpose): void
    {
        $this->sent[] = compact('channel', 'destination', 'code', 'purpose');
    }

    public function lastCode(): ?string
    {
        $last = end($this->sent);
        return $last ? $last['code'] : null;
    }
}
