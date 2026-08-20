# DMA — Delapan Mata Air

Aplikasi manajemen **studio foto sekolah** (booking, order, pelaksanaan event, dan keuangan) untuk DMA Delapan Mata Air. Dibangun dengan Laravel + Livewire dan berjalan multi-cabang.

---

## Fitur utama

- **Booking dua jalur** — order bisa dibuat lewat storefront oleh **sekolah** sendiri atau lewat panel oleh **marketing**, keduanya bermuara ke satu order + `booking_code`.
- **Katalog** — produk, paket (dipecah otomatis jadi produk saat order), desain, kategori, dan aturan bonus/free.
- **Manajemen order** — alur milestone H-7 / H-2 / Hari-H, penugasan marketing & tim event, kunci order setelah final.
- **Workflow tim event** — konfirmasi lokasi, revisi detail, penyelesaian via **OTP**, penanda "sampai kantor".
- **Keuangan** — pencatatan pembayaran (DP/pelunasan), **diskon per-produk** (ajukan → setujui), serta laporan penjualan harian & per-event.
- **Routing per kecamatan** — order sekolah otomatis diarahkan ke marketing sesuai kecamatan; data kecamatan bisa diimpor dari public API wilayah.
- **Notifikasi WhatsApp** — OTP & konfirmasi dikirim via WhatsApp (gateway Fonnte), dengan fallback tampil di portal sekolah.
- **Dashboard & audit** — dashboard per peran/cabang, laporan order per produk (dengan nominal), dan log aktivitas order.
- **Kontrol akses** — peran terpusat (lintas cabang) maupun terikat cabang, ditegakkan lewat `CabangScope` + policy.

## Teknologi

| Bagian | Teknologi |
|---|---|
| Framework | Laravel 13 (PHP 8.3) |
| UI interaktif | Livewire 4 + Alpine.js |
| Styling | Tailwind CSS |
| Database | PostgreSQL |
| Otorisasi | spatie/laravel-permission |
| PDF & QR | barryvdh/laravel-dompdf, simplesoftwareio/simple-qrcode |

## Dua wajah aplikasi

- **Storefront** (`/`) — untuk sekolah: guard `sekolah`, katalog + keranjang + riwayat order.
- **Panel staf** (`/app`) — untuk internal: guard `web` + peran spatie, dibatasi per cabang via `CabangScope`.

## Peran pengguna

| Peran | Cakupan | Ringkas |
|---|---|---|
| `super_admin` | Semua cabang | Akses penuh |
| `operasional` | Semua cabang | Operasional pusat |
| `admin_sales` | Semua cabang | Milestone H-7/H-2, keuangan, override OTP |
| `editor` | Semua cabang | Katalog & desain |
| `marketing` | Per cabang | Booking, kelola order kecamatannya |
| `tim_event` | Per cabang | Pelaksanaan event & OTP |

---

## Menjalankan secara lokal

Prasyarat: PHP 8.3, Composer, Node.js, PostgreSQL.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Sesuaikan `.env` (koneksi PostgreSQL: `DB_CONNECTION=pgsql`), lalu:

```bash
php artisan migrate --seed
npm run dev
php artisan serve
```

## Konfigurasi penting (`.env`)

| Variabel | Keterangan |
|---|---|
| `DB_CONNECTION=pgsql` | Wajib PostgreSQL |
| `APP_LOCALE=id` | Format tanggal Bahasa Indonesia |
| `FONNTE_TOKEN` | Token device Fonnte untuk kirim WhatsApp (kosong = WA dilewati, OTP tetap tampil di portal) |
| `WILAYAH_API_BASE` | Basis public API wilayah (default emsifa) untuk impor kecamatan |

## Perintah berguna

```bash
php artisan test                 # jalankan seluruh test
php artisan kecamatan:import     # impor kecamatan riil dari public API wilayah
```

Peta kota → kode wilayah untuk impor kecamatan diatur di `config/wilayah.php`.

## Deploy

Aplikasi dijalankan dengan Docker (PHP-FPM + Nginx + PostgreSQL) di belakang domain ber-HTTPS. Alur rilis: kerja di `develop`, merge ke `main` saat rilis, lalu server menarik `main` dari repositori.
