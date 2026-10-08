# Penjelasan Singkat untuk Presentasi / Review

## 1. Pemetaan layar -> endpoint

| Layar (Figma) | Elemen UI | Endpoint |
|---|---|---|
| Login (Email) | Tombol "Masuk Sekarang" | `POST /auth/login` |
| Login (Email) | Banner "Autentikasi Gagal - Sisa 2x" | respons 401 `meta.remaining_attempts` |
| Login (Email) | "Lupa Kata Sandi?" | masuk ke flow Lupa Password |
| Login (WA) | "Kirim Kode OTP" | `POST /auth/login/whatsapp/request-otp` |
| Login (WA) | Input 6 digit OTP + "Coba Verifikasi Lagi" | `POST /auth/login/whatsapp/verify-otp` |
| Login (WA) | "Kirim Ulang via WhatsApp" | `POST /auth/otp/resend` |
| Login (WA) | "Kode Salah atau Kedaluwarsa... dibekukan 15 menit" | `OTP_INVALID` / `OTP_EXPIRED` / `OTP_ATTEMPTS_EXCEEDED` |
| Register | "Daftar Sekarang" | `POST /auth/register` |
| Register | "Sudah Terdaftar" (email & WA) | 422 `errors.email` / `errors.phone` |
| Lupa Password 1 | "Kirim Kode OTP" (toggle Email/WhatsApp) | `POST /auth/forgot-password` |
| Lupa Password 2 | "Verifikasi & Buat Sandi Baru", "Kirim Ulang Kode" | `POST /auth/forgot-password/verify-otp`, `POST /auth/otp/resend` |
| Lupa Password 3 | Kartu nama + "Terverifikasi", checklist kekuatan sandi, "Simpan Kata Sandi & Masuk" | respons verify-otp (`user`), lalu `POST /auth/reset-password` |

## 2. Alur tiap flow

**Register:** validasi -> normalisasi nomor -> cek unik email/WA -> hash password -> simpan -> token.

**Login Email:** cari user -> cek password (bcrypt) -> salah: hitung percobaan (maks 5, lalu kunci 15 menit) -> benar: buat token (24 jam / 30 hari jika "Ingat Saya").

**Login WA:** request OTP -> OTP 6 digit dibuat, di-hash, disimpan, dikirim -> user input kode -> cek kedaluwarsa, jumlah percobaan, kecocokan hash -> sukses: OTP ditandai terpakai, token dibuat.

**Lupa Password:** kirim OTP (email/WA) -> verifikasi OTP -> server memberi `reset_token` sekali pakai (15 menit) -> kirim password baru + token -> password diganti, token lama dicabut, user langsung login.

## 3. Pertanyaan yang sering muncul saat review

- **Kenapa OTP di-hash?** Jika database bocor, OTP aktif tidak bisa dibaca. Sama seperti password.
- **Kenapa ada `reset_token` terpisah?** Agar OTP hanya dipakai untuk membuktikan kepemilikan; langkah ganti password butuh bukti yang sudah diverifikasi, tidak bisa melompat ke langkah 3.
- **Bagaimana mencegah brute force OTP?** Maks 5 percobaan per OTP, lalu beku 15 menit; ditambah rate limit per IP.
- **Kenapa respons "kirim OTP" selalu sukses?** Mencegah user enumeration.
- **Kenapa Sanctum, bukan JWT?** Token bisa dicabut kapan saja (logout/reset password), sederhana, dan resmi dari Laravel.
- **Bagaimana kalau 2 request OTP benar datang bersamaan?** Klaim OTP memakai `UPDATE ... WHERE consumed_at IS NULL` (atomik); hanya satu yang berhasil.
- **Apa yang belum ada (pengembangan lanjut)?** Integrasi gateway WA sungguhan, riwayat password, verifikasi email setelah register, pembersihan OTP lama via scheduler, refresh token.
