# Laporan Progres Aplikasi DMA

_Per 2026-08-01. Status: pengembangan (lokal). **206 automated test lolos.**_
Aplikasi: **DMA (Delapan Mata Air) — Studio Foto Sekolah**, sistem booking multi-cabang (Laravel 13 + Livewire + PostgreSQL).

---

## A. Sudah dikerjakan — dipetakan ke flow DMA

### 1. Storefront belanja (sisi pelanggan/sekolah) ✅
- Halaman depan `/`: katalog + kategori bisa diklik + paket populer + cara pesan.
- Katalog publik + detail produk/paket (tanpa perlu login), tab kategori, pencarian.
- Keranjang tamu (session) → checkout.
- Desain e-commerce (navy/oranye, font Archivo) + logo & favicon DMA + PWA (bisa "install" di HP).

### 2. Akun sekolah ✅
- Daftar mandiri (email + password) → **verifikasi email** wajib sebelum pesan.
- Login, lupa/ganti sandi, profil sekolah (email guru = email login), riwayat booking + tiket QR.
- Kota → cabang otomatis; kalau kota "lainnya" → cabang di-assign admin dulu.

### 3. Katalog & produk (sisi admin) ✅
- CRUD Kategori, Produk (+ opsi ukuran, harga override), Paket, Desain (pool desain per kategori/tahun ajaran).
- **Satuan produk**: per-qty atau **per-jumlah-siswa** (harga × siswa) — sesuai pricelist 2026.
- **Free sekolah**: mekanisme item gratis otomatis (aturan paket + bonus produk).
- Seeder pricelist 2026 (12 kategori representatif) + pool desain contoh.

### 4. Booking → Order (dua jalur) ✅
- **Jalur sekolah** (storefront): sekolah pesan sendiri → order masuk `sumber=sekolah`, menunggu penugasan.
- **Jalur marketing** (panel staf): marketing buat order → langsung dapat kode booking.
- Harga dihitung di server (bukan dari sesi); item gratis dievaluasi otomatis.

### 5. Kotak masuk (penugasan) ✅
_Flow DMA: orderan masuk → assign marketing_
- Marketing **ambil** order (klaim atomik) / admin **tugaskan/ubah** ke marketing tertentu.
- **Modal detail order** (lihat item/sekolah sebelum ambil) + filter tanggal & pencarian + kartu per-cabang (admin).

### 6. Manajemen order (O1–O5) ✅
_Flow DMA: assign marketing → konfirmasi DP → H-7 → H-2 → Hari-H → STE_
- **Tanggal & jam event** diinput saat checkout, bisa diubah marketing.
- **Status pembayaran**: Menunggu DP → DP dibayar → Lunas → (Batal), dengan catatan.
- **Milestone H-7 / H-2 / Hari-H** + countdown + tanda "terlewat".
- **Assign tim event** (pivot) + **Cetak STE** (Surat Tugas Event PDF: detail sekolah, jadwal, tim, rincian pesanan, email guru).
- Halaman **Order** dengan filter lengkap (status pembayaran, status event, tahap milestone, rentang tanggal, cabang) — marketing hanya lihat order miliknya.
- Popup konfirmasi di tiap aksi order.

### 7. Workflow tim event (TE1–TE3) ✅
_Flow DMA: STE → tim event konfirmasi ulang di sekolah → DONE/REVISI → OTP → status DONE_
- Menu **Jadwal Event** + detail event (baca STE di layar + link Google Maps).
- **Konfirmasi detail di lokasi** (jalur DONE) / **Revisi** (edit nama+alamat sekolah + desain/kode item).
- **OTP penyelesaian**: generate → kode dikirim ke **guru (portal sekolah + email)** → tim event input OTP → **status event = Selesai** + waktu selesai tercatat.
  - Masa berlaku OTP **30 menit**, kirim ulang ada **cooldown 60 detik** (hitung mundur).
  - Kode **tidak** ditampilkan ke tim event (harus dari guru) → anti-kecurangan.
  - Admin bisa **override** (selesaikan tanpa OTP) bila perlu.
- Event hanya muncul di dashboard tim event bila order sudah di-assign marketing.

### 8. Log aktivitas & akuntabilitas ✅
- **Timeline "Riwayat & pihak terlibat"** di tiap order/event: siapa melakukan apa & kapan (dibuat, assign marketing, DP/lunas, milestone, tim event, konfirmasi lokasi, revisi, OTP, selesai).
- Halaman **Aktivitas** global (audit lintas order) + filter.

### 9. Dashboard per role ✅
- **Admin (super_admin/operasional/area)**: interaktif — filter cabang + rentang tanggal + toggle basis; **Perlu tindakan** (belum di-assign, event terlewat, menunggu DP, event minggu ini — klik ke daftar), **Agenda event terdekat**, **per-cabang**, **tren order masuk**, **kinerja marketing**, **aktivitas terbaru**.
- **Marketing**: statistik + order terbaru klik-able.
- **Tim event**: event ditugaskan + progres.

### 10. Master data, hak akses & email ✅
- CRUD Cabang, Pengguna (role + cabang), Sekolah; RBAC (spatie) + isolasi cabang otomatis (CabangScope).
- Sidebar dikelompokkan per fungsi (Operasional / Katalog / Data master).
- Email dev via Mailpit (verifikasi, OTP) — template email OTP siap.

---

## B. Belum ada / perlu dibangun

### B1. Bagian akhir flow DMA (gambar terakhir) — BELUM ❌
_Ini yang perlu didiskusikan lagi dengan DMA (lihat `HOSTING-VPS-DMA.md` bagian 8)._
- **Notifikasi otomatis kembali ke kantor** + **estimasi durasi (GPS/ETA)** dari lokasi event → kantor.
- **Laporan perjalanan** (estimasi vs aktual, keterlambatan/ketepatan) saat tim sampai kantor klik "selesai".
- **Download folder event** — struktur folder otomatis per order berisi note kode desain (contoh `10RP_WISUDA_MINIMALIS`).
- Catatan: bagian GPS ini **lompatan besar** — perlu keputusan platform (aplikasi native?), API peta, dan **kebijakan privasi lokasi karyawan**.

### B2. Fitur yang masih placeholder / belum jalan ❌
- **Proofing desain** — pratinjau & persetujuan desain oleh sekolah (masih "Segera hadir").
- **Pembayaran online / payment gateway** — saat ini DP/lunas dikonfirmasi **manual** oleh marketing; belum ada bayar online (Midtrans/Xendit/QRIS).
- **Riwayat/ledger pembayaran** formal (sebagian tercakup log aktivitas, tapi belum ada rekap keuangan/tagihan).
- **Dashboard editor** (role editor) — masih placeholder.
- **Notifikasi ke pelanggan/tim** (WhatsApp/push) — baru email OTP; belum ada notifikasi status ke sekolah.
- **Laporan/analitik lanjutan** (export Excel/PDF rekap, laporan keuangan per cabang).
- Kosmetik: nama bulan masih Inggris (locale belum 'id').

### B3. Fase 2 — Persiapan produksi (naik ke server) ❌
Aplikasi masih **berjalan di lokal (dev)**. Untuk dipakai DMA sungguhan perlu:
- Sewa **VPS + domain** (lihat `HOSTING-VPS-DMA.md`).
- **Deploy** aplikasi ke server + **SSL/HTTPS** (Let's Encrypt).
- **Email produksi** (layanan transaksional, mis. Brevo) agar verifikasi & OTP tak masuk spam.
- **Backup otomatis** + pemantauan uptime.
- **Hardening keamanan** (firewall, update rutin) + rencana maintenance.
- Impor data awal DMA yang sebenarnya (cabang, pengguna, kota, produk/pricelist final).

---

## C. Ringkas

| Bagian flow DMA | Status |
|---|---|
| Booking sekolah/marketing → order | ✅ Selesai |
| Assign marketing (kotak masuk) | ✅ Selesai |
| DP → H-7 → H-2 → Hari-H → STE | ✅ Selesai |
| Tim event konfirmasi ulang / revisi | ✅ Selesai |
| OTP → status DONE event | ✅ Selesai |
| GPS kembali ke kantor + laporan perjalanan | ❌ Belum (perlu keputusan DMA) |
| Download folder event | ❌ Belum (perlu aturan penamaan DMA) |
| Proofing desain, bayar online, notif WA | ❌ Belum (Fase lanjutan) |
| Naik ke server produksi (deploy) | ❌ Belum (Fase 2 persiapan) |

**Inti alur operasional dari orderan masuk sampai event selesai (OTP) sudah berfungsi penuh.** Sisanya: bagian akhir (GPS/folder) yang perlu diskusi DMA, beberapa fitur lanjutan, dan persiapan naik ke server.
