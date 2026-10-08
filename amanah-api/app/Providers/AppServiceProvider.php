<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Services\Otp\ChannelOtpSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpSender::class, ChannelOtpSender::class);
    }

    public function boot(): void
    {
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('otp', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
    }
}
