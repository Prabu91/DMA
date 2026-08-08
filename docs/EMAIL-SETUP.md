# DMA — Setup Email (Verifikasi & Notifikasi)

Kode email + verifikasi email sekolah **sudah selesai & jalan**. Yang membedakan lingkungan hanyalah **`.env`** (driver mail). Dokumen ini: **lokal (Mailpit)** untuk dev/tes, dan **VPS (SMTP asli)** untuk demo/produksi.

> Verifikasi email wajib sebelum checkout (keputusan pemilik). Registrasi sekolah → email verifikasi dikirim → klik tautan → akun aktif → boleh checkout.

---

## A. Lokal (dev) — Mailpit

**Mailpit** menangkap semua email keluar dan menampilkannya di UI web (tidak benar-benar mengirim ke internet). Ideal untuk tes register→verifikasi→order.

Sudah terpasang di: `D:\laragon\bin\mailpit\mailpit.exe`

### 1. Jalankan Mailpit
Klik dua kali **`D:\laragon\bin\mailpit\start-mailpit.bat`**, atau via terminal:
```
"D:\laragon\bin\mailpit\mailpit.exe" --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025 --db-file "D:\laragon\bin\mailpit\mailpit.db"
```
- SMTP: `127.0.0.1:1025`
- Web UI: **http://localhost:8025**

### 2. Setel `.env` (lokal)
Ubah 2 baris (host sudah benar):
```
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="no-reply@dma.test"
MAIL_FROM_NAME="Delapan Mata Air"
```
Lalu bersihkan cache config:
```
php artisan config:clear
```

### 3. Tes
1. Buka app di **http://dma.test:8080** (URL Laragon; jangan preview `:8123` agar link signed valid).
2. Daftar sekolah → cek **http://localhost:8025** → buka email verifikasi → klik tautannya.
3. Akun terverifikasi → login → belanja → checkout → order.

> Kalau `MAIL_MAILER=log` (default lama), email TIDAK dikirim — hanya ditulis ke `storage/logs/laravel.log`.

---

## B. VPS (demo / produksi) — SMTP asli

Di VPS, email verifikasi harus benar-benar sampai ke inbox pendaftar. Pakai provider SMTP.

### Rekomendasi: Brevo (ex-Sendinblue)
Gratis ~300 email/hari, deliverability baik, tanpa domain terverifikasi pun bisa mulai.

`.env` di VPS:
```
APP_URL=https://domain-demo-anda            # WAJIB benar → link verifikasi ikut host ini
APP_ENV=production
APP_DEBUG=false

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<login-smtp-brevo>
MAIL_PASSWORD=<kunci-smtp-brevo>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@domain-demo-anda"
MAIL_FROM_NAME="Delapan Mata Air"
```

Alternatif provider (pilih salah satu):
| Provider | Catatan |
|----------|---------|
| **Brevo** | 300/hari gratis; `smtp-relay.brevo.com:587` |
| **Mailtrap** | Punya *Email Testing* (dev) + *Email Sending* (prod) dalam 1 akun |
| **Gmail SMTP** | Cepat; `smtp.gmail.com:587` + app-password; limit 500/hari, rawan spam — hanya untuk demo kecil |
| **Amazon SES / Mailgun / Postmark / Resend** | Lebih produksi; perlu verifikasi domain |

### Checklist VPS
- [ ] `APP_URL` = domain HTTPS asli (kalau salah, tautan verifikasi salah host).
- [ ] Kredensial SMTP provider terisi.
- [ ] `MAIL_FROM_ADDRESS` idealnya di domain yang terverifikasi di provider (deliverability).
- [ ] `php artisan config:cache` setelah set `.env`.
- [ ] (Opsional) Antrikan email: set `QUEUE_CONNECTION=database` + jalankan `php artisan queue:work` agar registrasi tak menunggu kirim email. Untuk demo, `sync` (default) sudah cukup.
- [ ] Uji: daftar 1 akun → cek inbox asli → klik verifikasi.

---

## Ringkas
| Lingkungan | MAIL_MAILER | Host:Port | Lihat email |
|-----------|-------------|-----------|-------------|
| Lokal | `smtp` | `127.0.0.1:1025` (Mailpit) | http://localhost:8025 |
| VPS demo | `smtp` | provider (mis. `smtp-relay.brevo.com:587`) | inbox asli pendaftar |
