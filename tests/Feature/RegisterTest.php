<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\ApiTestCase;

class RegisterTest extends ApiTestCase
{
    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'email' => 'Budi.Santoso@gmail.com',
            'phone' => '812-3456-7890',
            'password' => 'Passw0rd!Strong',
            'password_confirmation' => 'Passw0rd!Strong',
            'terms_accepted' => true,
        ], $override);
    }

    public function test_register_success_hashes_password_and_normalizes_input(): void
    {
        $res = $this->postJson('/api/v1/auth/register', $this->payload());

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'phone'], 'token' => ['access_token', 'token_type', 'expires_at']]]);

        $user = User::firstWhere('email', 'budi.santoso@gmail.com');
        $this->assertNotNull($user);
        $this->assertSame('+6281234567890', $user->phone);
        $this->assertNotSame('Passw0rd!Strong', $user->password);
        $this->assertTrue(Hash::check('Passw0rd!Strong', $user->password));
        $res->assertJsonMissingPath('data.user.password');
    }

    public function test_register_rejects_duplicate_email_and_phone(): void
    {
        User::factory()->create(['email' => 'budi.santoso@gmail.com', 'phone' => '+6281234567890']);

        $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_register_rejects_weak_password(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['password' => 'password', 'password_confirmation' => 'password']))
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_register_requires_matching_confirmation_and_terms(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['password_confirmation' => 'Different1!', 'terms_accepted' => false]))
            ->assertStatus(422)->assertJsonValidationErrors(['password', 'terms_accepted']);
    }

    public function test_register_rejects_invalid_phone(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['phone' => '12345']))
            ->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }
}
