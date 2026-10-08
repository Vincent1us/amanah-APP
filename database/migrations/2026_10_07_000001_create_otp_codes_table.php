<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('channel', ['email', 'whatsapp']);
            $table->string('destination', 191);               // email atau nomor WA (E.164)
            $table->enum('purpose', ['login', 'reset_password']);
            $table->char('code_hash', 64);                    // HMAC-SHA256, OTP tidak disimpan plain
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('resend_available_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();     // terisi = OTP tidak bisa dipakai lagi
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['destination', 'purpose', 'id'], 'otp_lookup_idx');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
