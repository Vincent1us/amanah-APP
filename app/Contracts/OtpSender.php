<?php

namespace App\Contracts;

interface OtpSender
{
    /** @param 'email'|'whatsapp' $channel */
    public function send(string $channel, string $destination, string $code, string $purpose): void;
}
