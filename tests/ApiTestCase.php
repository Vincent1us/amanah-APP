<?php

namespace Tests;

use App\Contracts\OtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeOtpSender;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeOtpSender $otp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otp = new FakeOtpSender();
        $this->app->instance(OtpSender::class, $this->otp);
    }

    protected function wrongCodeFor(string $real): string
    {
        return $real === '000000' ? '111111' : '000000';
    }
}
