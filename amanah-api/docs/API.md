# Dokumentasi API - Amanah Auth

- Base URL (lokal): `http://localhost:8000/api/v1`
- Format: JSON (`Content-Type: application/json`, `Accept: application/json`)
- Autentikasi: **Bearer token** (Laravel Sanctum). Kirim header `Authorization: Bearer <access_token>` untuk endpoint bertanda 🔒.
- Spesifikasi lengkap (Swagger/OpenAPI): `docs/openapi.yaml`; Postman: `docs/postman_collection.json`

## Format Response

Sukses:
```json
{ "success": true, "message": "Login berhasil.", "data": { } }
```
Gagal:
```json
{
  "success": false,
  "message": "Pesan yang bisa ditampilkan ke pengguna",
  "code": "KODE_ERROR_UNTUK_FRONTEND",
  "errors": { "email": ["..."] },
  "meta":   { "remaining_attempts": 4, "retry_after": 60 }
}
```
`errors` hanya ada pada validasi (422). `meta` hanya ada jika relevan.

## Daftar Endpoint

| # | Method | Path | Layar | Auth |
|---|---|---|---|---|
| 1 | POST | `/auth/register` | Register | - |
| 2 | POST | `/auth/login` | Login (Email) | - |
| 3 | POST | `/auth/login/whatsapp/request-otp` | Login (WA) - Kirim Kode OTP | - |
| 4 | POST | `/auth/login/whatsapp/verify-otp` | Login (WA) - Verifikasi OTP | - |
| 5 | POST | `/auth/forgot-password` | Lupa Kata Sandi - Kirim Kode OTP | - |
| 6 | POST | `/auth/forgot-password/verify-otp` | Cek Email Anda - Verifikasi | - |
| 7 | POST | `/auth/reset-password` | Atur Kata Sandi Baru | - (pakai reset_token) |
| 8 | POST | `/auth/otp/resend` | "Kirim Ulang Kode" | - |
| 9 | GET | `/auth/me` | Profil user login | 🔒 |
| 10 | POST | `/auth/logout` | Logout | 🔒 |
| 11 | GET | `/health` | Health check | - |

## Aturan Umum

- **Nomor WhatsApp**: boleh `812-3456-7890`, `0812...`, `62812...`, atau `+62812...`; server menormalkan ke `+6281234567890`.
- **Password**: min 8 karakter, huruf besar & kecil, angka, dan simbol (sesuai checklist di layar).
- **OTP**: 6 digit, berlaku 5 menit, maks 5 kali salah, sekali pakai, kirim ulang tiap 60 detik.
- **Anti user-enumeration**: endpoint yang mengirim OTP (3, 5, 8) selalu membalas sukses meski akun tidak ada.

---

## 1. POST `/auth/register`

Request:
```json
{
  "name": "Budi Santoso",
  "email": "budi.santoso@gmail.com",
  "phone": "812-3456-7890",
  "password": "Passw0rd!Strong",
  "password_confirmation": "Passw0rd!Strong",
  "terms_accepted": true
}
```
**201 Created**
```json
{
  "success": true,
  "message": "Pendaftaran berhasil.",
  "data": {
    "user": { "id": 1, "name": "Budi Santoso", "email": "budi.santoso@gmail.com", "phone": "+6281234567890",
              "email_verified": false, "phone_verified": false, "created_at": "2026-10-07T04:00:00+00:00" },
    "token": { "access_token": "1|abc...", "token_type": "Bearer", "expires_at": "2026-10-08T04:00:00+00:00" }
  }
}
```
**422** (email/WA sudah terdaftar, password lemah, dll.)
```json
{
  "success": false, "message": "Validasi gagal. Periksa kembali data yang Anda kirim.", "code": "VALIDATION_ERROR",
  "errors": {
    "email": ["Email ini sudah terdaftar. Silakan gunakan email lain atau masuk."],
    "phone": ["Nomor WhatsApp ini sudah terdaftar di akun aktif."]
  }
}
```

## 2. POST `/auth/login`

Request: `{ "email": "budi.santoso@gmail.com", "password": "Password123!", "remember_me": true }`

**200 OK** - sama seperti response register (`user` + `token`). `remember_me=true` => token 30 hari, selain itu 24 jam.

**401** - Autentikasi Gagal (layar menampilkan "Sisa 2x")
```json
{ "success": false, "message": "Kombinasi email atau kata sandi tidak cocok. ...", "code": "INVALID_CREDENTIALS",
  "meta": { "remaining_attempts": 2 } }
```
**429** - `LOGIN_LOCKED` setelah 5 kali salah (dibekukan 15 menit). Header `Retry-After` dan `meta.retry_after` (detik).

## 3. POST `/auth/login/whatsapp/request-otp`

Request: `{ "phone": "812-3456-7890" }`

**200 OK**
```json
{ "success": true, "message": "Jika nomor terdaftar, kode OTP telah dikirim ke WhatsApp Anda.",
  "data": { "channel": "whatsapp", "masked_destination": "+62812****7890", "expires_in": 300, "resend_in": 60 } }
```
Bila `OTP_EXPOSE_IN_RESPONSE=true` (khusus development) ada tambahan `data.debug_otp`.

Error: 422 (format nomor salah), 429 `OTP_RESEND_TOO_SOON` (terlalu cepat meminta ulang).

## 4. POST `/auth/login/whatsapp/verify-otp`

Request: `{ "phone": "812-3456-7890", "code": "482910", "remember_me": false }`

**200 OK** - `user` + `token`.

| Status | code | Arti (layar "Kode OTP Tidak Sesuai") |
|---|---|---|
| 422 | `OTP_INVALID` | Kode salah. `meta.remaining_attempts` = sisa percobaan |
| 422 | `OTP_EXPIRED` | Kode kedaluwarsa (> 5 menit) |
| 422 | `OTP_NOT_FOUND` | Belum ada OTP / sudah dipakai / sudah diganti OTP baru |
| 429 | `OTP_ATTEMPTS_EXCEEDED` | 5x salah; dibekukan 15 menit (`meta.retry_after`) |

## 5. POST `/auth/forgot-password`

Request (email): `{ "method": "email", "email": "bud@gmail.com" }`
Request (WA): `{ "method": "whatsapp", "phone": "812-3456-7890" }`

**200 OK** - bentuk data sama seperti endpoint 3, mis. `"masked_destination": "bud***@gmail.com"`.

## 6. POST `/auth/forgot-password/verify-otp`

Request: `{ "method": "email", "email": "bud@gmail.com", "code": "482910" }`

**200 OK**
```json
{ "success": true, "message": "Verifikasi berhasil. Silakan atur kata sandi baru.",
  "data": { "reset_token": "<64 karakter>", "expires_at": "2026-10-07T04:15:00+00:00",
            "user": { "name": "Budi Santoso", "email_masked": "bud***@gmail.com", "verified": true } } }
```
Error: sama seperti tabel di endpoint 4.

## 7. POST `/auth/reset-password`

Request:
```json
{ "reset_token": "<dari langkah 6>", "password": "NewPassw0rd!", "password_confirmation": "NewPassw0rd!" }
```
**200 OK** - `user` + `token` (langsung login; sesuai tombol "Simpan Kata Sandi & Masuk"). Semua token lama user dicabut.

Error: 422 `RESET_TOKEN_INVALID` (salah / kedaluwarsa 15 menit / sudah dipakai); 422 `VALIDATION_ERROR` (password lemah, tidak cocok, atau **sama dengan password saat ini**).

## 8. POST `/auth/otp/resend`

Request: `{ "purpose": "login", "channel": "whatsapp", "destination": "+6281234567890" }`
`purpose`: `login` (hanya `whatsapp`) atau `reset_password` (`email`/`whatsapp`).
**200 OK** seperti endpoint 3. **429** `OTP_RESEND_TOO_SOON` bila belum 60 detik.

## 9. GET `/auth/me` 🔒 / 10. POST `/auth/logout` 🔒

`/me` => `{ "data": { "user": {...} } }`. `/logout` mencabut token yang sedang dipakai.
Tanpa/dengan token salah => **401** `UNAUTHENTICATED`.

---

## Tabel HTTP Status & Error Code

| HTTP | code | Kapan |
|---|---|---|
| 200 | - | Sukses |
| 201 | - | Register sukses |
| 401 | `INVALID_CREDENTIALS` | Email/password salah |
| 401 | `UNAUTHENTICATED` | Token tidak ada / tidak valid / kedaluwarsa |
| 404 | `NOT_FOUND` | Endpoint tidak ada |
| 405 | `METHOD_NOT_ALLOWED` | Method salah |
| 422 | `VALIDATION_ERROR` | Input tidak valid |
| 422 | `OTP_INVALID` / `OTP_EXPIRED` / `OTP_NOT_FOUND` | Masalah OTP |
| 422 | `RESET_TOKEN_INVALID` | Token reset tidak valid |
| 429 | `LOGIN_LOCKED` / `OTP_ATTEMPTS_EXCEEDED` / `OTP_RESEND_TOO_SOON` / `TOO_MANY_REQUESTS` | Pembatasan percobaan / rate limit |
| 500 | `SERVER_ERROR` | Error server (detail disembunyikan di production) |
| 502 | `OTP_DELIVERY_FAILED` | Gateway WhatsApp gagal |
