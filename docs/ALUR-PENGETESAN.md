# DMA — Alur Pengetesan Semua Fungsi (Manual QA)

Panduan uji end-to-end untuk seluruh fungsi aplikasi DMA: **storefront publik**, **auth & area akun sekolah**, **checkout**, dan **panel staf**. Dipakai bersama regresi otomatis (`php artisan test`).

> Konvensi: setiap kasus punya kode (mis. `SF-01`). Tandai `[x]` bila lolos, catat temuan di kolom Catatan.
> Legend hasil: **✅ sesuai** · **⚠️ beda kecil** · **❌ gagal**.

---

## 0. Persiapan lingkungan

| # | Langkah | Perintah |
|---|---------|----------|
| P1 | Siapkan DB bersih + data contoh | `php artisan migrate:fresh --seed` |
| P2 | Build aset frontend | `npm run build` |
| P3 | Jalankan dev server (preview) | preview `dma-serve` (port **8123**) atau `php artisan serve` |
| P4 | Regresi otomatis harus hijau dulu | `php artisan test` → **143 lolos** |

### Akun uji (password semua: `password`)

**Staf (guard `web`, login di `/login`)** — area slug: `jks, bdg, bgr, cjr, bks, sby`:

| Peran | Email | Fungsi kunci |
|-------|-------|--------------|
| super_admin | `superadmin@dma.test` | semua akses, CRUD cabang/pengguna, katalog global |
| operasional | `operasional@dma.test` | katalog global, kotak masuk, assign |
| area | `area.jks@dma.test` | dashboard area, assign/reassign order cabangnya |
| marketing | `marketing1.jks@dma.test`, `marketing2.jks@dma.test` | ambil order, booking marketing |
| tim_event | `event.jks@dma.test` | dashboard event, selesaikan event |
| editor | `editor.jks@dma.test` | dashboard editor |

**Sekolah (guard `sekolah`, login di `/masuk`)**: tidak di-seed — **daftar mandiri** lewat `/daftar` (bagian B), atau buat via tinker:

```php
php artisan tinker --execute="
\$c = App\Models\Cabang::first();
\$s = App\Models\Sekolah::firstOrNew(['email'=>'demo@contoh.sch.id']);
\$s->fill(['id_sekolah'=>\$s->id_sekolah ?: App\Models\Sekolah::generateIdSekolah(),'nama'=>'SD Demo','cabang_id'=>\$c->id,'password'=>'password']);
\$s->email_verified_at = now(); \$s->save(); echo 'OK '.\$s->email;"
```

> **Catatan preview :8123**
> - Tautan verifikasi email **signed** bisa mismatch karena `APP_URL=http://localhost` (artefak port, bukan bug). Untuk uji cepat, verifikasi via tinker (`->markEmailAsVerified()`) atau andalkan test otomatis.
> - Bila klik tombol Livewire tak bereaksi (sintetis tak ter-delegate), panggil `window.Livewire.find(id).call('metode')` di console.

---

## A. Storefront publik (tamu, tanpa login)

| Kode | Tujuan | Langkah | Hasil diharapkan | Hasil | Catatan |
|------|--------|---------|------------------|:----:|---------|
| SF-01 | Home katalog | Buka `/` sebagai tamu | Hero "Booking foto sekolah", section **Jelajahi kategori** (kategori berproduk + jumlah), **Paket unggulan** (bila ada), **Cara pesan** (4 langkah) | | |
| SF-02 | Header e-commerce | Perhatikan header `/` | Logo, menu **Katalog**, **ikon keranjang** (+badge bila ada isi), tombol **Masuk**/**Daftar** | | |
| SF-03 | Staf diarahkan | Login staf lalu buka `/` | Redirect ke `app.dashboard` (staf tak melihat storefront) | | |
| SF-04 | Katalog publik | Klik **Katalog** / `/katalog` | Daftar produk per kategori + paket, tanpa login; kartu menuju `/katalog/{tipe}/{id}` | | |
| SF-05 | Cari katalog | Ketik di kotak "Cari…" | Daftar terfilter live (nama produk/paket) | | |
| SF-06 | Detail produk polos | Buka produk non-berdesain | Info, harga, opsi ukuran (bila ada), tombol **Tambah ke keranjang**, link **Lihat keranjang** | | |
| SF-07 | Detail produk berdesain | Buka produk kategori `pakai_desain` | Muncul **Pilih desain** (pool tahun ajaran aktif) + selektor tahun; validasi wajib pilih desain | | |
| SF-08 | Opsi wajib | Produk dengan opsi `is_wajib` | Klik Tambah tanpa memilih ukuran → error "Silakan pilih ukuran (wajib)" | | |
| SF-09 | Tambah ke keranjang (guest) | Klik **Tambah ke keranjang** | Pesan "Ditambahkan ke keranjang"; badge keranjang header bertambah | | |
| SF-10 | Halaman keranjang | Buka `/keranjang` | Item tampil; **tanpa** "Booking untuk"/"Jumlah siswa"; ada subtotal, CTA **Lanjut ke pemesanan** | | |
| SF-11 | Ubah qty | Klik +/− pada item | Total item & subtotal dihitung ulang di server | | |
| SF-12 | Hapus & kosongkan | Klik hapus / Kosongkan keranjang | Item hilang; keranjang kosong menampilkan empty state + link katalog | | |
| SF-13 | Keranjang kosong empty state | Buka `/keranjang` tanpa isi | "Keranjang masih kosong" + "Jelajahi katalog", subtotal Rp0 | | |

---

## B. Registrasi & verifikasi email sekolah

| Kode | Tujuan | Langkah | Hasil diharapkan | Hasil | Catatan |
|------|--------|---------|------------------|:----:|---------|
| RG-01 | Form daftar | Buka `/daftar` | Form registrasi mandiri (nama, email, kota, password, dst.) | | |
| RG-02 | Daftar kota terpetakan | Daftar dgn kota Jakarta/Bandung/dll | Akun dibuat, `id_sekolah` = `SKL-0000xx`, `cabang_id` sesuai kota | | |
| RG-03 | Daftar kota "lainnya" | Daftar dgn kota tak terpetakan | Akun dibuat dengan `cabang_id = null` (akan diblokir saat checkout — lihat CO-04) | | |
| RG-04 | Email verifikasi terkirim | Setelah daftar | Diarahkan ke `/verifikasi` (notice); email verifikasi dikirim (cek log/mailer) | | |
| RG-05 | Verifikasi email | Klik tautan verifikasi (atau tinker `markEmailAsVerified`) | `email_verified_at` terisi; akses konten `/sekolah/*` terbuka | | |
| RG-06 | Kirim ulang | Di `/verifikasi`, klik kirim ulang | Throttle `6,1`; email dikirim ulang | | |

---

## C. Login sekolah & login-gate checkout

| Kode | Tujuan | Langkah | Hasil diharapkan | Hasil | Catatan |
|------|--------|---------|------------------|:----:|---------|
| LG-01 | Login benar | `/masuk` dgn email+password valid | Masuk; redirect ke `intended` atau `sekolah.beranda` | | |
| LG-02 | Login salah | Kredensial salah | Error "Email atau kata sandi salah." | | |
| LG-03 | Login-gate dari keranjang | Isi keranjang → **Lanjut ke pemesanan** | Redirect ke `/checkout` → (tamu) redirect ke `/masuk`; `url.intended` = `/checkout` | | |
| LG-04 | Kembali ke checkout | Login dari LG-03 | **Kembali otomatis ke `/checkout`**, keranjang **utuh** (session regenerate, bukan invalidate) | | |
| LG-05 | Logout | Klik **Keluar** | Logout; redirect `storefront.home` | | |

---

## D. Checkout (reuse mesin order — sumber `sekolah`)

Prasyarat: login sebagai sekolah **terverifikasi** dengan keranjang berisi item.

| Kode | Tujuan | Langkah | Hasil diharapkan | Hasil | Catatan |
|------|--------|---------|------------------|:----:|---------|
| CO-01 | Checkout tampil | Buka `/checkout` (verified + cabang + ada item) | Halaman **Checkout**: "Booking untuk" (sekolah sendiri), item, input **Jumlah siswa**, ringkasan, **Simpan booking** | | |
| CO-02 | Belum verifikasi diblokir | Login sekolah **belum** verified → `/checkout` | Redirect ke `sekolah.verification.notice` (**wajib verifikasi**) | | |
| CO-03 | Keranjang kosong | `/checkout` dgn keranjang kosong | Redirect ke `/keranjang` | | |
| CO-04 | Cabang null diblokir | Sekolah `cabang_id = null` → `/checkout` | Halaman blokir "**Cabang belum ditetapkan**" + arahan hubungi admin; **tanpa** form review | | |
| CO-05 | Wajib jumlah siswa | Klik **Simpan** tanpa isi jumlah siswa | Error "Isi jumlah siswa terlebih dahulu."; order **tidak** dibuat | | |
| CO-06 | Free sekolah terhitung | Isi jumlah siswa memenuhi aturan free | Section **Item gratis** muncul (FreeSekolahEvaluator), total dihitung server | | |
| CO-07 | Simpan order (happy path) | Isi jumlah siswa → **Simpan** | Order tersimpan: `sumber=sekolah`, `marketing_id=null`, `cabang_id` dari sekolah, `status=baru`, `booking_code=null`; keranjang dikosongkan | | |
| CO-08 | Halaman sukses | Setelah CO-07 | Redirect `sekolah.riwayat.show`: badge **"Menunggu penugasan marketing"** | | |

Verifikasi DB (opsional): `select sumber, marketing_id, cabang_id, status, jumlah_siswa, booking_code from orders order by id desc limit 1;`

---

## E. Area akun sekolah

Prasyarat: login sekolah terverifikasi.

| Kode | Tujuan | Langkah | Hasil diharapkan | Hasil | Catatan |
|------|--------|---------|------------------|:----:|---------|
| AK-01 | Beranda | `/sekolah/beranda` | Identitas (ID, **status cabang**, PIC) + tautan Profil/Katalog/Riwayat | | |
| AK-02 | Navigasi dropdown | Buka menu akun (avatar) | Beranda, Katalog, Riwayat booking, **Profil sekolah**, Ganti kata sandi, Keluar | | |
| AK-03 | Profil tampil | `/sekolah/profil` | Identitas akun read-only (ID, email login, kota, cabang) + form kontak | | |
| AK-04 | Update profil | Ubah PIC/telepon/alamat → **Simpan perubahan** | "Tersimpan."; data persist (cek ulang/DB) | | |
| AK-05 | Validasi profil | Kosongkan **Nama** → simpan | Error "nama wajib" | | |
| AK-06 | Profil tanpa verifikasi | Login belum-verified → `/sekolah/profil` | Boleh diakses (profil & ganti sandi di luar gate `verified.sekolah`) | | |
| AK-07 | Riwayat list | `/sekolah/riwayat` | Daftar order milik sendiri (isolasi per `sekolah_id`), status, tanggal, jumlah item/siswa, total | | |
| AK-08 | Detail order | Buka salah satu order | Status, item (paid+free), ringkasan; **Proofing desain — Segera hadir** (placeholder) | | |
| AK-09 | Isolasi antar sekolah | Coba buka `/sekolah/riwayat/{id-milik-sekolah-lain}` | 404 (hanya order milik sendiri) | | |
| AK-10 | PDF order | Bila `booking_code` ada, klik **Unduh/cetak PDF** | Nota/invoice + QR ter-render (`/sekolah/riwayat/{id}/pdf`) | | |
| AK-11 | Ganti kata sandi | `/sekolah/password`, isi lama+baru | "Tersimpan"; login berikutnya pakai sandi baru | | |

---

## F. Panel staf — autentikasi & routing

| Kode | Tujuan | Langkah | Hasil diharapkan | Hasil | Catatan |
|------|--------|---------|------------------|:----:|---------|
| ST-01 | Login staf | `/login` dgn `superadmin@dma.test` | Masuk → `app.dashboard` sesuai role | | |
| ST-02 | Guard terpisah | Login staf, buka `/masuk` | Tetap area sekolah (guard beda), tak bentrok sesi | | |
| ST-03 | Proteksi role | Login `marketing1.jks`, buka `/app/cabang` | 403 / tidak diizinkan (hanya super_admin) | | |
| ST-04 | CabangScope | Login `area.jks`, buka data sekolah/order | Hanya melihat cabang JKS (super_admin/operasional lihat semua) | | |

---

## G. Panel staf — Kotak masuk (claim/assign) → booking_code → PDF

Prasyarat: minimal 1 order jalur sekolah dari bagian D (mis. cabang Jaksel).

| Kode | Tujuan | Langkah | Hasil diharapkan | Hasil | Catatan |
|------|--------|---------|------------------|:----:|---------|
| KM-01 | Lihat kotak masuk | Login staf JKS → `/app/kotak-masuk` | Tab **Baru** menampilkan order `sumber=sekolah` belum ditugaskan (ter-scope cabang) | | |
| KM-02 | Marketing "Ambil" | Login `marketing1.jks` → klik **Ambil** | Klaim atomik (`marketing_id` = dirinya); **booking_code** ter-generate (`DDMMYY+area+role+urut`) | | |
| KM-03 | Anti-bentrok | Marketing lain coba ambil order sama | "Order sudah diambil marketing lain atau tidak tersedia." | | |
| KM-04 | Admin assign | Login `area.jks`/operasional → pilih marketing → **Tugaskan** | Order ditugaskan; booking_code dibuat; hanya marketing **secabang** valid | | |
| KM-05 | Assign lintas cabang ditolak | Pilih marketing cabang lain | "Pilih marketing yang sesuai cabang terlebih dahulu." | | |
| KM-06 | Reassign | Order sudah ditugaskan → ganti marketing | "Penugasan diperbarui." | | |
| KM-07 | PDF staf | Buka order → `/app/order/{id}/pdf` | Nota/invoice + QR (booking_code) | | |
| KM-08 | Sinkron ke sekolah | Login sekolah pemilik → riwayat order | Status kini menampilkan **booking_code** + QR (bukan lagi "menunggu") | | |

---

## H. Panel staf — Booking jalur marketing (dibuatkan staf)

Prasyarat: login `marketing1.jks` (atau area/operasional/super_admin).

| Kode | Tujuan | Langkah | Hasil diharapkan | Hasil | Catatan |
|------|--------|---------|------------------|:----:|---------|
| BM-01 | Etalase staf | `/app/etalase` | Katalog konteks staf; tombol keranjang | | |
| BM-02 | Tambah item | Buka detail → Tambah ke keranjang | Item masuk keranjang session | | |
| BM-03 | Keranjang staf | `/app/keranjang` | Ada **pilih sekolah** (cabangnya) + **jumlah siswa** | | |
| BM-04 | Review & simpan | Pilih sekolah + jumlah siswa → **Lanjut ke review** → **Simpan** | Order `sumber=marketing`, `marketing_id` terisi → **booking_code langsung** dibuat | | |
| BM-05 | Detail order staf | `/app/order/{id}` | Status + booking_code + QR + PDF | | |

---

## I. Panel staf — CRUD master data

Prasyarat: login `super_admin` (cabang/pengguna) atau `super_admin`/`operasional` (katalog global).

| Kode | Area | Route | Uji | Hasil | Catatan |
|------|------|-------|-----|:----:|---------|
| MD-01 | Cabang | `/app/cabang` | Create/edit/hapus cabang (kode_area unik) | | |
| MD-02 | Pengguna | `/app/pengguna` | Create/edit user + assign role + cabang | | |
| MD-03 | Kategori | `/app/katalog/kategori` | CRUD kategori (`pakai_desain`) | | |
| MD-04 | Produk | `/app/katalog/produk` | CRUD produk + opsi ukuran + bonus + upload foto | | |
| MD-05 | Paket | `/app/katalog/paket` | CRUD paket + pivot produk | | |
| MD-06 | Desain | `/app/katalog/desain` | CRUD desain (kode unik, tahun ajaran, tempel kategori) | | |
| MD-07 | Aturan free | `/app/katalog/aturan-free` | CRUD aturan free sekolah (basis qty/omset) | | |

---

## J. Panel staf — Dashboard per role & tim event

| Kode | Tujuan | Langkah | Hasil diharapkan | Hasil | Catatan |
|------|--------|---------|------------------|:----:|---------|
| DB-01 | Dashboard sesuai role | Login tiap role → `/app/dashboard` | Diarahkan ke dashboard role masing-masing (mis. `dashboard.super_admin`) | | |
| DB-02 | Statistik lintas cabang | super_admin/operasional | Panel lintas cabang tampil | | |
| DB-03 | Tim event selesai | `event.jks` → Jadwal Event → detail → konfirmasi lokasi → **Generate OTP** → input OTP dari guru → **Selesaikan event** | `event_status` jadi `selesai`; OTP tampil di portal sekolah + email guru | | |

---

## K. Regresi otomatis (wajib hijau)

```bash
php artisan test
```

Cakupan test relevan storefront (subset dari 143 test):

| Fase | File test | Fokus |
|------|-----------|-------|
| 1 | `KatalogHomeFase1Test` | home katalog + header |
| 2 | `KatalogPublikFase2Test` | katalog/detail publik + add-to-cart + validasi desain |
| 3 | `KeranjangPublikFase3Test` | keranjang guest, ubah qty/hapus, login-gate |
| 4 | `CheckoutPublikFase4Test` | login-gate, verified gate, cabang null, simpan order |
| 5 | `AkunSekolahFase5Test` | profil sekolah view/update/validasi/akses |
| — | `SekolahAuthTest`, `BookingFase*`, `KotakMasukFase5Test`, `BranchIsolationTest`, dll | auth, mesin order, assign, isolasi cabang |

---

## Lampiran — Peta route

| Area | Route (nama) | URL |
|------|--------------|-----|
| Storefront | `storefront.home` | `/` |
| | `storefront.katalog.index` | `/katalog` |
| | `storefront.katalog.detail` | `/katalog/{tipe}/{id}` |
| | `storefront.keranjang` | `/keranjang` |
| | `storefront.checkout` | `/checkout` |
| Auth sekolah | `sekolah.daftar` / `sekolah.masuk` / `sekolah.logout` | `/daftar` `/masuk` `/keluar` |
| | `sekolah.verification.notice` / `.verify` / `.send` | `/verifikasi*` |
| Akun sekolah | `sekolah.beranda` / `sekolah.katalog.*` | `/sekolah/beranda` `/sekolah/katalog` |
| | `sekolah.keranjang` / `sekolah.review` | `/sekolah/keranjang` `/sekolah/review` |
| | `sekolah.riwayat.index/show/pdf` | `/sekolah/riwayat*` |
| | `sekolah.profile.edit/update` / `sekolah.password.*` | `/sekolah/profil` `/sekolah/password` |
| Staf | `app.dashboard` / `app.kotak-masuk` | `/app/dashboard` `/app/kotak-masuk` |
| | `app.etalase.*` / `app.keranjang` / `app.review` / `app.order.*` | `/app/etalase` `/app/keranjang` `/app/order/{id}` |
| | `app.sekolah.index` / `app.cabang.*` / `app.pengguna.*` | `/app/sekolah` `/app/cabang` `/app/pengguna` |
| | `app.kategori/produk/paket/desain/aturan-free.index` | `/app/katalog/*` |
