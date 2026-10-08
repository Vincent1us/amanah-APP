<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun demo untuk mencoba di Postman.
        User::updateOrCreate(['email' => 'budi.santoso@gmail.com'], [
            'name'              => 'Budi Santoso',
            'phone'             => '+6281234567890',
            'password'          => 'Password123!',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
    }
}
