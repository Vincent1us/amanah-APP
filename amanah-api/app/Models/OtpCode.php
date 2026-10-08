<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_RESET = 'reset_password';

    protected $fillable = [
        'user_id', 'channel', 'destination', 'purpose', 'code_hash', 'attempts',
        'expires_at', 'resend_available_at', 'verified_at', 'consumed_at', 'ip_address',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at'          => 'datetime',
            'resend_available_at' => 'datetime',
            'verified_at'         => 'datetime',
            'consumed_at'         => 'datetime',
        ];
    }
}
