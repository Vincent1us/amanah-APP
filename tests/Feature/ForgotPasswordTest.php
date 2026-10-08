<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\ApiTestCase;

class ForgotPasswordTest extends ApiTestCase
{
    private function forgot(string $email = 'bud@gmail.com')
    {
        return $this->postJson('/api/v1/auth/forgot-password', ['method' => 'email', 'email' => $email]);
    }

    private function verify(string $code, string $email = 'bud@gmail.com')
    {
        return $this->postJson('/api/v1/auth/forgot-password/verify-otp', ['method' => 'email', 'email' => $email, 'code' => $code]);
    }

    private function reset(string $token, string $password = 'NewPassw0rd!')
    {
        return $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => $token, 'password' => $password, 'password_confirmation' => $password,
        ]);
    }

    private function getResetToken(): string
    {
        $this->forgot();
        return $this->verify($this->otp->lastCode())->json('data.reset_token');
    }

    public function test_full_flow_changes_password_and_logs_in(): void
    {
        $user = User::factory()->create(['email' => 'bud@gmail.com']);
        $old = $user->createToken('old')->plainTextToken;

        $this->forgot()->assertOk()->assertJsonPath('data.masked_destination', 'bud***@gmail.com');
        $this->assertSame('email', $this->otp->sent[0]['channel']);

        $verified = $this->verify($this->otp->lastCode())->assertOk()
            ->assertJsonPath('data.user.verified', true)->assertJsonPath('data.user.name', $user->name);
        $token = $verified->json('data.reset_token');

        $this->reset($token)->assertOk()->assertJsonStructure(['data' => ['token' => ['access_token']]]);

        // password baru berlaku, password lama tidak, token lama dicabut
        $this->postJson('/api/v1/auth/login', ['email' => 'bud@gmail.com', 'password' => 'NewPassw0rd!'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => 'bud@gmail.com', 'password' => 'Password123!'])->assertStatus(401);
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'old']);
    }

    public function test_reset_token_is_single_use(): void
    {
        User::factory()->create(['email' => 'bud@gmail.com']);
        $token = $this->getResetToken();

        $this->reset($token)->assertOk();
        $this->reset($token, 'AnotherPassw0rd!')->assertStatus(422)->assertJsonPath('code', 'RESET_TOKEN_INVALID');
    }

    public function test_reset_token_expires(): void
    {
        User::factory()->create(['email' => 'bud@gmail.com']);
        $token = $this->getResetToken();

        $this->travel(16)->minutes();

        $this->reset($token)->assertStatus(422)->assertJsonPath('code', 'RESET_TOKEN_INVALID');
    }

    public function test_new_password_must_differ_from_current(): void
    {
        User::factory()->create(['email' => 'bud@gmail.com']); // password: Password123!
        $token = $this->getResetToken();

        $this->reset($token, 'Password123!')->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_weak_new_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'bud@gmail.com']);
        $token = $this->getResetToken();

        $this->reset($token, 'weak')->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_wrong_and_expired_otp_are_rejected(): void
    {
        User::factory()->create(['email' => 'bud@gmail.com']);
        $this->forgot();
        $code = $this->otp->lastCode();

        $this->verify($this->wrongCodeFor($code))->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID');

        $this->travel(6)->minutes();
        $this->verify($code)->assertStatus(422)->assertJsonPath('code', 'OTP_EXPIRED');
    }

    public function test_unknown_email_gets_generic_response_without_sending_otp(): void
    {
        $this->forgot('ghost@gmail.com')->assertOk();
        $this->assertCount(0, $this->otp->sent);
    }

    public function test_whatsapp_method_works_and_validates_required_fields(): void
    {
        User::factory()->create(['phone' => '+6281234567890']);

        $this->postJson('/api/v1/auth/forgot-password', ['method' => 'whatsapp'])
            ->assertStatus(422)->assertJsonValidationErrors(['phone']);

        $this->postJson('/api/v1/auth/forgot-password', ['method' => 'whatsapp', 'phone' => '0812-3456-7890'])->assertOk();
        $this->assertSame('whatsapp', $this->otp->sent[0]['channel']);
    }
}
