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
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OtpSender::class, ChannelOtpSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}