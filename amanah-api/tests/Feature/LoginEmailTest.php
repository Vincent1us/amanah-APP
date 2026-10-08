<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\ApiTestCase;

class LoginEmailTest extends ApiTestCase
{
    public function test_login_success_returns_token_that_can_access_protected_route(): void
    {
        User::factory()->create(['email' => 'budi@domain.id']);

        $res = $this->postJson('/api/v1/auth/login', ['email' => 'budi@domain.id', 'password' => 'Password123!'])
            ->assertOk()->assertJsonPath('success', true);

        $token = $res->json('data.token.access_token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.email', 'budi@domain.id');
    }

    public function test_login_wrong_password_returns_401_with_remaining_attempts(): void
    {
        User::factory()->create(['email' => 'budi@domain.id']);

        $this->postJson('/api/v1/auth/login', ['email' => 'budi@domain.id', 'password' => 'Salah123!'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS')
            ->assertJsonPath('meta.remaining_attempts', 4);
    }

    public function test_login_is_locked_after_too_many_failures(): void
    {
        User::factory()->create(['email' => 'budi@domain.id']);
        $bad = ['email' => 'budi@domain.id', 'password' => 'Salah123!'];

        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/api/v1/auth/login', $bad)->assertStatus(401);
        }
        $this->postJson('/api/v1/auth/login', $bad)->assertStatus(429)->assertJsonPath('code', 'LOGIN_LOCKED');

        // Walau password benar, tetap diblokir selama masa lockout.
        $this->postJson('/api/v1/auth/login', ['email' => 'budi@domain.id', 'password' => 'Password123!'])
            ->assertStatus(429);
    }

    public function test_login_unknown_email_is_same_error_as_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@domain.id', 'password' => 'Salah123!'])
            ->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_login_validation_errors(): void
    {
        $this->postJson('/api/v1/auth/login', [])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_protected_route_requires_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_logout_revokes_token(): void
    {
        User::factory()->create(['email' => 'budi@domain.id']);
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'budi@domain.id', 'password' => 'Password123!'])
            ->json('data.token.access_token');

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
