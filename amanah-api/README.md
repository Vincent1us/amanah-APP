# Amanah Auth API (Laravel)

REST API untuk flow **Login Email**, **Login WhatsApp + OTP**, **Register**, dan **Lupa Password + OTP**.

**Stack:** PHP 8.2+, Laravel 11/12, Laravel Sanctum (Bearer token), MySQL 8 / PostgreSQL 14+.

## Isi paket ini

Paket ini berisi **kode aplikasi Amanah saja** (folder `app`, `routes`, `database`, `tests`, dll.) yang ditempelkan ke project Laravel yang baru dibuat. Folder `vendor` tidak disertakan (dibuat otomatis oleh Composer).

```
app/            Controller, FormRequest (validasi), Service (OTP), Model, Support
bootstrap/app.php   Konfigurasi routing + error handler JSON global
config/amanah.php   Pengaturan OTP, lockout, durasi token
database/       Migration, factory, seeder
routes/api.php  Daftar endpoint
tests/          Feature test (flow utama)
docs/           DATABASE.md, erd.png, API.md, openapi.yaml, postman_collection.json, PENJELASAN.md
scripts/install.sh  Skrip instalasi otomatis
```

## Instalasi

Prasyarat: PHP >= 8.2 (ext: mbstring, xml, curl, pdo_mysql / pdo_pgsql, sqlite untuk test), Composer, MySQL atau PostgreSQL.

### Cara 1 - otomatis (Linux / macOS / Git Bash)
```bash
bash scripts/install.sh amanah-app
```

### Cara 2 - manual (semua OS, termasuk Windows PowerShell)
```bash
composer create-project laravel/laravel:^12.0 amanah-app
# Salin isi paket ini ke dalam amanah-app (timpa jika ditanya):
#   app, bootstrap, config, database, routes, tests, docs, phpunit.xml, .env.example
cd amanah-app
composer require laravel/sanctum
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
```

### Konfigurasi database
1. Buat database kosong, mis. `amanah_db`.
2. Edit `.env` (`DB_CONNECTION=mysql` atau `pgsql`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

### Migrasi & jalankan
```bash
php artisan migrate --seed    # --seed membuat akun demo
php artisan serve             # http://localhost:8000
```
Akun demo: `budi.santoso@gmail.com` / `Password123!` / WA `+6281234567890`.

> Jangan jalankan `php artisan vendor:publish --tag=sanctum-migrations` - tabel `personal_access_tokens` sudah ada di migration kita.

## Mencoba API

- **Postman:** Import `docs/postman_collection.json`. Variabel `base_url`, `access_token`, `otp_code`, `reset_token` terisi otomatis oleh script test.
- **Swagger:** buka https://editor.swagger.io lalu File > Import file `docs/openapi.yaml`.
- **Dokumentasi lengkap:** `docs/API.md`.

### Soal pengiriman OTP saat development
Tanpa gateway WhatsApp/SMTP, OTP bisa dilihat dengan 2 cara:
1. `OTP_EXPOSE_IN_RESPONSE=true` di `.env` => kode muncul di `data.debug_otp` (dipakai Postman otomatis).
2. Lihat `storage/logs/laravel.log` (driver `WHATSAPP_DRIVER=log`, `MAIL_MAILER=log`).

**Production:** set `OTP_EXPOSE_IN_RESPONSE=false`, `APP_DEBUG=false`, `WHATSAPP_DRIVER=http` + isi `WHATSAPP_API_URL`/`WHATSAPP_API_TOKEN` (gateway seperti Fonnte/Twilio/WABA), dan isi konfigurasi SMTP.

## Testing
```bash
php artisan test
```
Memakai SQLite in-memory (tidak menyentuh database asli) dan `FakeOtpSender` (OTP tidak dikirim sungguhan). Cakupan: register, login email (sukses, gagal, lockout, logout), login WhatsApp (sukses, OTP salah/kedaluwarsa/sekali pakai/dibekukan/cooldown), lupa password (alur penuh, token sekali pakai & kedaluwarsa, password sama dengan lama, validasi).

## Fitur keamanan & ketentuan tugas

| Ketentuan | Implementasi |
|---|---|
| Validasi input | `FormRequest` per endpoint, pesan Bahasa Indonesia, normalisasi nomor WA & email |
| Password hashing | bcrypt via cast `hashed`; aturan password min 8 + besar/kecil + angka + simbol |
| Authentication | Sanctum Bearer token, kedaluwarsa 24 jam / 30 hari (`remember_me`), logout mencabut token |
| OTP expiration & validation | 6 digit CSPRNG, kedaluwarsa 5 menit, maks 5 salah (lalu beku 15 menit), sekali pakai, hanya hash disimpan, OTP baru membatalkan yang lama, cooldown kirim ulang 60 dtk |
| Error handling | Handler global di `bootstrap/app.php` + `ApiException`; format JSON seragam dengan `code` |
| HTTP status | 200/201/401/404/405/422/429/500/502 sesuai kasus |
| Lainnya | Lockout login (5x salah), rate limit per IP, anti user-enumeration & timing attack, reset token sekali pakai, semua token dicabut setelah reset password |

## Keputusan desain yang perlu diketahui

1. **Respons generik** pada kirim-OTP (login WA, lupa password, resend): selalu sukses walau akun tidak ada, agar penyerang tidak bisa mengecek email/nomor mana yang terdaftar. Jika ingin menampilkan "nomor belum terdaftar", ubah di `OtpService::request()`.
2. **Layar login memakai label "Email atau Username"**, tetapi layar register tidak punya field username, jadi login memakai **email**.
3. **Lupa password 3 langkah**: kirim OTP -> verifikasi OTP (dapat `reset_token`) -> set password baru (langsung login), mengikuti 3 layar pada flow.
4. Pengecekan "password harus berbeda dari yang pernah dipakai" saat ini membandingkan dengan password **saat ini**. Untuk riwayat penuh, tambahkan tabel `password_histories`.
5. Lockout login memakai cache (`RateLimiter`); di production gunakan cache `redis` agar konsisten antar server.

## Troubleshooting

| Masalah | Solusi |
|---|---|
| `could not find driver` | Aktifkan `pdo_mysql` / `pdo_pgsql` di php.ini |
| `Table 'personal_access_tokens' already exists` | Hapus migration Sanctum hasil publish (jika ada), jalankan `php artisan migrate:fresh --seed` |
| `Class "Laravel\Sanctum\HasApiTokens" not found` | Jalankan `composer require laravel/sanctum` |
| Test gagal `could not find driver (sqlite)` | Aktifkan `pdo_sqlite` di php.ini |
| Respons HTML bukan JSON | Pastikan URL diawali `/api/v1/...` |
