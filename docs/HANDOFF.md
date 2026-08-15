# DMA — Handoff untuk chat baru

Sistem operasional & booking studio foto DMA (multi-cabang). "Satu project dua wajah":
**Storefront** publik/sekolah di `/`, **Panel staf** di `/app`.

> **⚠️ BACA INI DULU — status per Agustus 2026.** Sebagian detail teknis di bawah ditulis **11 Jul 2026** dan **sudah usang**. Sumber status paling akurat:
> - **`docs/LAPORAN-PROGRES-DMA.md`** — apa yang sudah/belum dibangun (dipetakan ke flow DMA) + Fase 2.
> - **`docs/HOSTING-VPS-DMA.md`** — kebutuhan server/VPS + pertanyaan untuk DMA.
> - **Folder memory** (`MEMORY.md` + file per-topik) — keputusan & progres per fase (auto-load di chat baru).
>
> Koreksi cepat vs teks lama di bawah:
> - Test kini **±206 lolos** (bukan 114).
> - Storefront `/` **sudah jadi** (bukan placeholder): home katalog, katalog+detail, keranjang, checkout, area akun sekolah. Redesign navy/oranye + Archivo tuntas (D1–D5, adopsi Jonas J1–J7).
> - Palet: brand **oranye `#F7941D`** + **navy `#2E3192`/`#191B52`**; **storefront pakai font Archivo bold**; panel staf tetap Plus Jakarta. (Aturan lama "brand `#E08020` / maksimal `font-medium`" **tidak berlaku** untuk storefront.)
> - Konsep **"beranda" sekolah DIHAPUS** → area akun sekolah = **Riwayat + Profil**.
> - Sudah dibangun setelah Jul: **manajemen order** (DP/lunas/milestone H-7/H-2/HH/STE), **workflow tim event + OTP penyelesaian** (portal+email, 30 mnt + cooldown), **satuan produk qty/siswa** + pricelist 2026, **dashboard admin per-cabang interaktif** + **log aktivitas order** + halaman Aktivitas, sidebar bergrup.
> - Branding: "Reservasi Paket Foto" → **"Studio Foto"**. Belum: folder event/GPS, proofing desain, bayar online, deploy produksi.

## Stack & lingkungan
- Laravel 13, **Livewire 4 (class-based components** di `app/Livewire`, BUKAN single-file), Tailwind **v3.4** (via PostCSS; paket `@tailwindcss/vite` v4 ada tapi tidak dipakai), PostgreSQL, Breeze (Blade) + spatie/laravel-permission.
- OS Windows. Shell: Git Bash + PowerShell. `psql` di `/d/laragon/bin/postgresql/pgsql/bin/psql`, `PGPASSWORD=root`, user `postgres`.
- DB dev: `dma`. DB test: **`dma_testing`** (phpunit.xml pakai pgsql—mesin ini **tak punya `pdo_sqlite`**).
- **JANGAN sentuh `.env`.** Postgres kadang "connection refused" transien → cek service `postgresql-x64-18`, listen `127.0.0.1:5432`, lalu ulangi.
- Test: `php artisan test` (**114 lolos, ~321 assertions**). Build: `npm run build`. Kompilasi cek sintaks: `php artisan view:cache && php artisan view:clear`.
- Preview: `mcp__Claude_Browser__preview_start` name `dma-serve` (launch.json port 8123). Catatan: **klik sintetis `wire:click` tidak ter-delegate** → panggil `window.Livewire.find(id).call('metode', ...)`. Tautan **signed** (verifikasi email) tak cocok di `:8123` karena `APP_URL=http://localhost` (artefak port, bukan bug).

## Arsitektur dua area (SUDAH JADI)
- **Storefront `/`** (publik + guard `sekolah`). Staf (web) yang buka `/` → redirect `app.dashboard`. Layout: `<x-storefront-layout>` (hangat/e-commerce). Halaman `/` masih **placeholder** (`resources/views/storefront/home.blade.php`).
- **Panel staf `/app/*`**, nama route ber-prefix **`app.`** (guard web + spatie + CabangScope). Layout `<x-app-layout>` (sidebar+topbar+bottom-nav, indikator cabang). Semua CRUD master, dashboard per-role, etalase/booking staf, kotak masuk ada di sini.
- **Auth Breeze staf tetap di ROOT** (`/login`, `/register`, `/password/*`, `/verify-email`) — nama route bare (`login`, `register`, `verification.*`) agar test Breeze tak pecah. Login staf → `app.dashboard`.
- **Auth sekolah (storefront):** `/masuk` (login EMAIL+password), `/daftar` (registrasi mandiri), `/keluar` (logout), `/verifikasi/*` (verifikasi email). Konten portal sekolah masih di `/sekolah/*` (beranda/katalog/keranjang/review/riwayat) — middleware `auth:sekolah` + `verified.sekolah`.
- Guards: `web` (User+spatie), `sekolah` (model `Sekolah`, `implements MustVerifyEmail`). `config/auth.php` punya guard+provider `sekolah`.
- `bootstrap/app.php`: alias `role/permission/verified.sekolah`; `redirectGuestsTo` → area sekolah (`sekolah/*`,`verifikasi*`,`keluar`) ke `sekolah.masuk`, selain itu `login`. `AppServiceProvider`: `RedirectIfAuthenticated` guard-aware + `VerifyEmail::createUrlUsing` (Sekolah → route storefront).

### Konvensi route penting
- Staf: **`app.*`** (mis. `app.dashboard`, `app.cabang.index`, `app.kotak-masuk`, `app.etalase.index`, `app.order.pdf`). URL `/app/...`.
- Storefront/portal sekolah: **`sekolah.*`** (`sekolah.masuk`, `sekolah.daftar`, `sekolah.beranda`, `sekolah.katalog.index`, `sekolah.riwayat.*`, `sekolah.verification.*`, `sekolah.logout`).
- Komponen Livewire dipakai-ulang lintas area lewat prop **`konteks`** ('staf'→`app.*`, 'sekolah'→`sekolah.*`). Contoh: `Etalase`, `EtalaseDetail`, `Keranjang`, `Review`, `OrderDetail`, `Riwayat`.
- `RoleMenu::for()` mem-prefix `app.` terpusat.

## Design system (WAJIB dipakai, jangan styling ad-hoc)
Komponen `resources/views/components/`: `x-button` (variant primary/secondary/ghost/danger, size, href), `x-input` (label/name/error otomatis; saat `wire:model` tanpa `name` jangan panggil `old()`), `x-select`, `x-card` (title/actions slot), `x-badge` (status/brand/navy/neutral), `x-avatar` (inisial), `x-stat-card`, `x-brand-logo` (prop `tone` dark/light).
Token (`tailwind.config.js` + CSS var di `resources/css/app.css`): `brand #E08020` (aksi), `navy`, `ink`/`ink-muted`, `page`/`card`/`line`, `status.{success,pending,info,danger}`. `darkMode:'class'`. Radius: `rounded-lg`=8px, `rounded-xl`=12px. Font **Plus Jakarta Sans 400 & 500 saja** → **maksimal `font-medium`** (jangan bold/semibold). Sentence case, mobile-first.
**Alpine disediakan Livewire** (`@livewireScripts` di layout; `app.js` TIDAK start Alpine — hindari dobel).

## Domain (15 tabel, `docs/DMA.sql`)
- **Katalog GLOBAL** (tanpa CabangScope, kelola super_admin & operasional): `kategori` (pakai_desain), `produk` (+`produk_opsi` ukuran, +`produk_bonus`, foto di `storage/app/public/produk`, gaya MINIMALIS/BLOK/3D/GLITER/LEMBARAN), `paket` (pivot `paket_produk`), `desain` (menempel ke kategori, kode unik app-level, filter tahun_ajaran), `aturan_free_sekolah`.
- **Sekolah** per-cabang (`#[ScopedBy(CabangScope)]`). `id_sekolah` = **kode akun global `SKL-000001`** (`Sekolah::generateIdSekolah()`, dipakai registrasi + CRUD staf). Kolom baru Fase 0: `email` (unique, login), `email_verified_at`, `google_id` (unique, future Socialite—BELUM dibangun), `avatar`; `password` nullable.
- **Order** scoped. `sumber` ('sekolah'|'marketing'), `marketing_id` nullable. `order_items` (tipe_item paket|produk, FK nullable, `desain_id`, `opsi_ukuran` snapshot string, `is_free`).
- **Booking engine:** `App\Support\Cart` (session), `BookingContext` (resolusi jalur dari guard), `App\Services\BookingService` (resolveLines harga efektif = opsi.harga_override ?? produk.harga; evaluasiFree; `simpan` transaksional), `App\Services\FreeSekolahEvaluator` (A: aturan paket qty/omset; B: produk_bonus per-unit), `App\Services\CodeGenerator` (booking_code = `DDMMYY+kode_area+kode_role+urutan3`, mis. `060726JKTMKT1001`, anti-bentrok `pg_advisory_xact_lock`), `App\Support\Qr` (SVG), `OrderPdfController` (laravel-dompdf, nota+invoice+QR).
- **booking_code** dibuat saat `marketing_id` terisi: jalur marketing → di `BookingService::simpan`; jalur sekolah → saat **ambil/tugaskan** di `KotakMasuk` (klaim **atomik** `WHERE marketing_id IS NULL`).
- CabangScope = **web-only** (`Auth::user()` default guard). Isolasi sekolah via query eksplisit `where('sekolah_id', auth('sekolah')->id())`.

## Data seeder (setelah `migrate:fresh --seed`)
Cabang final: **Jaksel(JKS), Bandung(BDG), Bogor(BGR), Cianjur(CJR), Bekasi(BKS), Surabaya(SBY)**. Kota→cabang: Bandung→Bandung, Bogor→Bogor, Cianjur→Cianjur, Cikarang→Bekasi, Jakarta/Tangerang/Depok→Jaksel. **Cirebon di-skip** (cabang TBD).
Akun demo (password `password`): `superadmin@dma.test`, `operasional@dma.test`, `marketing1.<area>@dma.test`/`marketing2.<area>@dma.test`, `area.<area>@dma.test`, dst (area = jks/bdg/bgr/cjr/bks/sby). marketing `kode_role` = MKT1/MKT2.

## Sudah selesai
Aplikasi lengkap dari fase awal: auth staf, skema+model+relasi, RBAC+policy+CabangScope, design system, CRUD master data (kategori/sekolah/produk/paket/desain/aturan-free), mesin order end-to-end (keranjang → free eval → hitung server → simpan transaksional → kotak masuk & claim/assign → booking_code+QR → PDF), kedua jalur (marketing & sekolah).
**Rework "dua wajah" (prompt terakhir):** Fase 0 (skema sekolah storefront + tabel kota + rekonsiliasi cabang), Fase 1 (pisah `/app` panel + `/` storefront + layout + isolasi guard), Fase 2 (auth sekolah: registrasi mandiri `/daftar`, login email `/masuk`, verifikasi email `/verifikasi/*`, id_sekolah global).

## BERIKUTNYA / TODO
- **Bangun storefront belanja nyata** di `/` pakai komponen katalog/booking bersama dengan **layout storefront** (saat ini konten booking sekolah masih di portal `/sekolah/*` bergaya panel). Kemungkinan fase lanjutan dari pemilik.
- **Google login (Socialite)** — skema siap (`google_id`,`avatar`,`password` nullable) + komentar penanda; belum dibangun (cocokkan by email).
- **Cirebon**: tambah 1 baris di `KotaSeeder` bila cabangnya sudah ditentukan.
- Ikon PWA **PNG 192/512** (sekarang hanya SVG `public/icons/icon.svg`).
- Status pembayaran/DP masih **provisional** di `App\Support\OrderStatus` (fitur pembayaran/proofing/status-history = domain "Fase 2" yang belum dibangun; foto masih storage lokal, R2 nanti).
- `resources/views/welcome.blade.php` sudah tak dipakai (route `/` → storefront).

## RENCANA FASE BERIKUTNYA (storefront belanja ala Jonas) — prompt sudah disiapkan pemilik
`/` = HOME KATALOG (browse terbuka tanpa login; login diminta saat CHECKOUT). Keranjang guest (session), dipertahankan setelah login. Order sekolah → `sumber='sekolah'`, `marketing_id=null`, `cabang_id` dari sekolah → kotak masuk cabang (mekanisme claim/assign yg SUDAH ADA). **JANGAN bikin mesin order baru.**
- **Fase 1**: `/` jadi home katalog (hero + kategori + unggulan + "cara pesan"); header e-commerce (logo, menu katalog, ikon keranjang+jumlah, Masuk/Daftar); layout storefront.
- **Fase 2**: katalog publik + detail (pilih desain pool tahun aktif + opsi ukuran/is_wajib); reuse view etalase.
- **Fase 3**: keranjang guest (session) tanpa login; ikon keranjang di header.
- **Fase 4**: login-gate checkout → /masuk|/daftar lalu KEMBALI ke checkout (keranjang utuh); input jumlah siswa; review (FreeSekolahEvaluator + total di server); simpan Order via mesin yg ada (transaction); halaman berhasil status "menunggu penugasan marketing".
- **Fase 5**: area akun sekolah (profil + riwayat order + detail/status; placeholder tautan proofing).

### Reuse (JANGAN duplikasi) & GOTCHA penting
- Reuse: `App\Support\Cart` (session — **sudah guest-capable**), `Etalase`/`EtalaseDetail` (tambah-keranjang sudah ada), `BookingContext`, `BookingService::simpan`, `FreeSekolahEvaluator`, `Review`, `OrderDetail`, `Riwayat`, `KotakMasuk`, `CodeGenerator`, `OrderPdfController`. Tak perlu ubah engine.
- **Konteks komponen**: `Etalase`/`EtalaseDetail`/`Keranjang`/`Review`/`OrderDetail` sekarang hanya `konteks` **'staf'|'sekolah'** → membangun URL `app.*`/`sekolah.*`. Untuk storefront publik **tambah konteks 'publik'** + **route storefront publik** (browse/detail/keranjang TANPA auth). Jangan pakai route `/app/*` (role-gated) atau `/sekolah/*` (auth+verified) untuk halaman publik.
- **Keranjang lintas login**: `Auth::guard('sekolah')->login()` + `session()->regenerate()` mempertahankan data session (hanya rotasi ID) → cart aman. Pastikan tak memanggil `session()->invalidate()` saat login. Untuk "kembali ke checkout": pakai `redirect()->intended()` / simpan intended URL.
- **GOTCHA cabang null**: `orders.cabang_id` **NOT NULL**. Sekolah yang daftar dgn kota **"lainnya"** punya `cabang_id=null` → **checkout akan gagal**. Wajib tangani: blokir checkout + pesan "cabang belum ditetapkan, hubungi admin" (atau admin assign cabang dulu). **KONFIRMASI ke pemilik.**
- **GOTCHA verifikasi email**: konten `/sekolah/*` digerbang `verified.sekolah`. Putuskan apakah **checkout boleh untuk sekolah belum-verifikasi** (disarankan boleh order, verifikasi bisa menyusul) atau wajib verified dulu. **KONFIRMASI ke pemilik.** Kalau checkout dibuat di route storefront publik (bukan /sekolah/*), gate ini tak otomatis kena — atur sengaja.
- **Storefront layout** (`resources/views/layouts/storefront.blade.php`) perlu ditambah: menu katalog + **ikon keranjang dgn badge jumlah** (`app(Cart::class)->count()`).
- `welcome.blade.php` sudah tak dipakai — Fase 1 mengganti isi `/` (`storefront.home`).
- Semua tetap **pakai design system** + storefront layout (hangat), mobile-first, ramah guru.

## Aturan kerja pemilik
Kerjakan **bertahap per fase**; setelah tiap fase: laporkan ringkas + **BERHENTI** tunggu review. Jelaskan keputusan penting sebelum eksekusi. Konfirmasi hal yang diminta. Selalu jalankan test + verifikasi live (preview) tiap fase. Jangan sentuh `.env`.
