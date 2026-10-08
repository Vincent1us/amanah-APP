<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'phone'             => '+62812'.fake()->unique()->numerify('########'),
            'password'          => 'Password123!', // di-hash otomatis oleh cast 'hashed'
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ];
    }
}
