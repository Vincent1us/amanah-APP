<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\ApiTestCase;

class LoginWhatsappTest extends ApiTestCase
{
    private const PHONE = '+6281234567890';

    private function requestOtp(string $phone = '812-3456-7890')
    {
        return $this->postJson('/api/v1/auth/login/whatsapp/request-otp', ['phone' => $phone]);
    }

    private function verify(string $code)
    {
        return $this->postJson('/api/v1/auth/login/whatsapp/verify-otp', ['phone' => self::PHONE, 'code' => $code]);
    }

    public function test_full_login_flow(): void
    {
        User::factory()->create(['phone' => self::PHONE]);

        $this->requestOtp()->assertOk()->assertJsonPath('data.channel', 'whatsapp')->assertJsonPath('data.expires_in', 300);
        $this->assertCount(1, $this->otp->sent);
        $this->assertSame(self::PHONE, $this->otp->sent[0]['destination']);

        $code = $this->otp->lastCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertDatabaseMissing('otp_codes', ['code_hash' => $code]); // OTP tidak disimpan plain

        $this->verify($code)->assertOk()->assertJsonStructure(['data' => ['token' => ['access_token']]]);
    }

    public function test_unregistered_phone_gets_generic_response_and_no_otp_sent(): void
    {
        $this->requestOtp()->assertOk();
        $this->assertCount(0, $this->otp->sent);
    }

    public function test_wrong_code_returns_422_with_remaining_attempts(): void
    {
        User::factory()->create(['phone' => self::PHONE]);
        $this->requestOtp();

        $this->verify($this->wrongCodeFor($this->otp->lastCode()))
            ->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID')->assertJsonPath('meta.remaining_attempts', 4);
    }

    public function test_expired_code_is_rejected(): void
    {
        User::factory()->create(['phone' => self::PHONE]);
        $this->requestOtp();
        $code = $this->otp->lastCode();

        $this->travel(6)->minutes();

        $this->verify($code)->assertStatus(422)->assertJsonPath('code', 'OTP_EXPIRED');
    }

    public function test_code_is_single_use(): void
    {
        User::factory()->create(['phone' => self::PHONE]);
        $this->requestOtp();
        $code = $this->otp->lastCode();

        $this->verify($code)->assertOk();
        $this->verify($code)->assertStatus(422)->assertJsonPath('code', 'OTP_NOT_FOUND');
    }

    public function test_too_many_wrong_attempts_lock_the_otp_even_for_the_correct_code(): void
    {
        User::factory()->create(['phone' => self::PHONE]);
        $this->requestOtp();
        $code = $this->otp->lastCode();
        $wrong = $this->wrongCodeFor($code);

        for ($i = 1; $i <= 4; $i++) {
            $this->verify($wrong)->assertStatus(422);
        }
        $this->verify($wrong)->assertStatus(429)->assertJsonPath('code', 'OTP_ATTEMPTS_EXCEEDED');
        $this->verify($code)->assertStatus(429);

        // Dalam masa lockout, minta OTP baru juga ditolak.
        $this->requestOtp()->assertStatus(429)->assertJsonPath('code', 'OTP_RESEND_TOO_SOON');
    }

    public function test_resend_respects_cooldown_and_invalidates_previous_code(): void
    {
        User::factory()->create(['phone' => self::PHONE]);
        $this->requestOtp()->assertOk();
        $first = $this->otp->lastCode();

        $this->requestOtp()->assertStatus(429)->assertJsonPath('code', 'OTP_RESEND_TOO_SOON');

        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/otp/resend', ['purpose' => 'login', 'channel' => 'whatsapp', 'destination' => self::PHONE])->assertOk();
        $this->assertCount(2, $this->otp->sent);

        if ($first !== $this->otp->lastCode()) {
            $this->verify($first)->assertStatus(422); // kode lama tidak berlaku lagi
        }
        $this->verify($this->otp->lastCode())->assertOk();
    }

    public function test_invalid_phone_format_is_rejected(): void
    {
        $this->requestOtp('abc')->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }
}
