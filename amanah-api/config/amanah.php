<?php

return [
    'otp' => [
        'length'                 => 6,
        'expires_minutes'        => (int) env('OTP_EXPIRES_MINUTES', 5),
        'max_attempts'           => (int) env('OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN', 60),
        'lockout_minutes'        => (int) env('OTP_LOCKOUT_MINUTES', 15),
        // HANYA untuk development/Postman. Wajib false di production.
        'expose_in_response'     => (bool) env('OTP_EXPOSE_IN_RESPONSE', false),
        'whatsapp' => [
            'driver'      => env('WHATSAPP_DRIVER', 'log'), // log | http
            'url'         => env('WHATSAPP_API_URL'),
            'token'       => env('WHATSAPP_API_TOKEN'),
            'auth_header' => env('WHATSAPP_AUTH_HEADER', 'Authorization'),
        ],
    ],
    'login' => [
        'max_attempts'    => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
    ],
    'reset_token_minutes' => (int) env('RESET_TOKEN_MINUTES', 15),
    'token_ttl' => [
        'default_hours' => 24,
        'remember_days' => 30,
    ],
];
