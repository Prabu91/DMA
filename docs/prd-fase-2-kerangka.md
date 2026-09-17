# PRD Fase 2 — DMA

> Kerangka untuk diisi bersama DMA. Contoh yang ada di tiap bagian diambil dari
> kasus nyata Fase 1 — hapus setelah bagian itu terisi.
>
> Aturan main: **setiap fitur wajib punya contoh dengan angka/nama sungguhan.**
> Kalau contohnya belum bisa dibuat, kebutuhannya belum matang — tandai sebagai
> "perlu digali", jangan dimasukkan ke ruang lingkup.

| | |
| --- | --- |
| Versi | draf 2 — hasil bedah board Trello (A, C, E) |
| Tanggal | 16 September 2026 |
| Disusun | Faris |
| Disetujui | (nama + tanggal) |

Tanda di dokumen ini: **✅ disepakati** · **❓ perlu dikonfirmasi ke DMA** · **🔍 perlu digali**

---

## 1. Tujuan Fase 2

Perjalanan order setelah booking saat ini dikelola di Trello: sekitar 40 board,
satu board per tahap, dan setiap kartu diketik ulang dari data yang sebenarnya
sudah ada di sistem. Akibatnya data tercatat dua kali, isian kartu tidak
seragam (harga ditulis sebagai teks, custom field bertambah tanpa aturan),
tenggat H+1/H+3/H+4 hanya berupa tulisan di nama board, dan tidak ada satu
tempat untuk melihat satu order dari ujung ke ujung.

Fase 2 memindahkan perjalanan order itu ke dalam sistem sebagai papan kanban
yang **kartunya dibuat otomatis dari order**, sehingga setiap divisi tetap
punya "board"-nya sendiri, tetapi datanya satu.

**Ukuran keberhasilan** (draf, ❓ disepakati bersama DMA):

1. Tidak ada lagi data order yang diketik ulang ke kartu — kartu muncul sendiri saat order dibuat.
2. Posisi setiap order (tahap + penanggung jawab) bisa dilihat dari satu layar, termasuk yang lewat tenggat.
3. Board Trello untuk tahap yang sudah dipindahkan berhenti dipakai.

---

## 2. Ruang lingkup

Diurutkan dari yang paling merugikan kalau tidak ada.

| # | Fitur | Kenapa perlu | Siapa yang memakai | Definisi selesai |
| - | ----- | ------------ | ------------------ | ---------------- |
| 1 | **Order susulan** terhubung ke order induk | Susulan terjadi setiap pekan dan sekarang tercecer (label, komentar, kartu terpisah) | marketing, tim event, editor | Dari order induk bisa dibuat susulan; keduanya saling menunjuk; susulan langsung ke hari event |
| 2 | **QC item per peran** | Kartu Trello berisi 3 checklist kembar (Marketing, Team Event, Admin) untuk item yang sama | tim event, admin collection | Tiap item punya centang per peran, tercatat siapa & kapan; progres tampil di kartu |
| 3 | **Redaksi & path folder otomatis** | REDAKSI.txt dan path folder editor diketik manual per kartu | admin collection, editor | Redaksi terisi dari data sekolah; path folder dibuat dari data order |
| 4 | **Papan kanban — mesin dasar** | Pengganti Trello | semua divisi | Kolom per tahap / per penanggung jawab, kartu otomatis, flag tertahan, tenggat, filter cabang & jalur produk |
| 5 | **Tahap C — Collect Admin** | Tahap pertama setelah event | admin collection | Kelengkapan berkas + seleksi foto; lengkap → otomatis ke Siap Edit |
| 6 | **Tahap E — Editing** | Tahap produksi utama | SPV editor, editor | Siap Edit → kolom per editor; tenggat opsional |
| 7 | **Panel staf pindah ke subdomain** | Memisahkan area kerja staf dari storefront sekolah | semua staf | Panel staf hanya di subdomain; alamat `/app` lama dialihkan |
| 8 | Tahap F–P | Sisa perjalanan order | QC, produksi, delivery, finance | 🔍 menunggu screenshot board masing-masing |

---

## 3. Aturan bisnis

### 3.1 Aturan uang

| Skenario | Masukan | Hasil yang benar |
| -------- | ------- | ---------------- |
| ✅ Harga susulan | Induk: Pas Foto Rp10.000 (sudah dikoreksi admin dari katalog Rp12.000). Susulan 2 anak | Susulan memakai **Rp10.000** — disalin dari item induk, bukan harga katalog terbaru |
| ✅ Item gratis pada susulan | Induk mendapat "10RP Free Sekolah 1 pcs" dari aturan free sekolah. Susulan 2 anak | Susulan **tidak** mendapat item gratis lagi — bonusnya sudah diberikan di induk |
| ✅ Item susulan berbeda dari induk | Induk: pas foto + foto kelas. Susulan: pas foto + produk yang tidak ada di induk | Boleh. Produk yang juga ada di induk memakai harga induk; ❓ produk baru memakai harga katalog |
| ✅ DP | Order dengan DP dibayar setelah event | Tidak menghalangi milestone apa pun (sudah berlaku sejak Fase 1) |

> Contoh dari Fase 1:
> | Yearbook + box | 60 HALAMAN (Rp320.000, mengganti) + BOX (Rp40.000, menambah) | Rp360.000 |
> | Pas foto | 20 pcs dibagi 2x3 (4) + 3x4 (2), harga paket Rp20.000 | Rp20.000 — komposisi tidak mengubah harga |

### 3.2 Aturan hak akses

| Peran | Boleh | Tidak boleh |
| ----- | ----- | ----------- |
| Marketing | ✅ Membuat order susulan | |
| Tim event | ✅ Centang QC item di Hari-H | Centang QC milik admin |
| Admin collection | ✅ Centang QC item di H+1, kelengkapan berkas, seleksi foto | |
| SPV editor | ✅ Memindahkan kartu dari Siap Edit ke editor mana pun | |
| Editor | ✅ Mengambil kartu dari Siap Edit untuk dirinya sendiri | ❓ Memindahkan kartu ke editor lain |
| ❓ Penugasan tim event untuk susulan | Sekarang hanya admin (super admin/operasional/admin sales) | Perlu dikonfirmasi apakah marketing boleh |

> Perhatikan khusus: apa pun yang lintas cabang. Salah di sini berarti data satu
> cabang terlihat oleh cabang lain.

### 3.3 Aturan data

| Data | Aturan | Alasan |
| ---- | ------ | ------ |
| ✅ Centang QC item | Lepas otomatis bila jumlah item diubah | Item tidak boleh tetap "sesuai" padahal jumlahnya berubah |
| ✅ Redaksi | Koreksi per order tidak mengubah data sekolah | Nama cetak bisa berbeda dari nama di data induk sekolah |
| ✅ Order susulan | Tidak melewati H-7/H-2; STE tetap terbit | Susulan kecil dan jadwalnya mepet; tim event tetap butuh surat tugas |
| ✅ Order susulan | Konfirmasi data sekolah & QC di Hari-H tetap berlaku | Itu pemeriksaan di lokasi, bukan urutan jadwal |
| ✅ Kolom papan | Tetap, tidak bisa ditambah pengguna | Laporan "berapa order tertahan di tahap X" hanya bisa dipercaya bila kolomnya tetap |

---

## 4. Skenario pemakaian

**Skenario 1 — Susulan (dari kartu TK Plus Lestari)**

- Situasi: event TK Plus Lestari (marketing Rudi, Bandung 2) 14 September 2026, pas foto 50 pcs, 2 anak tidak hadir.
- Yang dilakukan: Rudi membuka order induk → "Buat order susulan" → isi pas foto 2 pcs + tanggal susulan.
- Yang diharapkan: order susulan terisi sekolah, cabang, desain dari induk; harga pas foto sama dengan induk; tidak ada item gratis; langsung bisa dijadwalkan tanpa H-7/H-2; STE bisa dicetak; induk menampilkan "1 susulan".
- Kalau salah: susulan tertahan menunggu H-2, atau sekolah mendapat bonus gratis dua kali.

**Skenario 2 — QC item (dari kartu TK Miftahul Khoir)**

- Situasi: 7 item, termasuk "Souvenir Free Poster 63 pcs".
- Yang dilakukan: tim event mencentang 7 item di Hari-H; admin collection mencentang ulang di H+1 berdasarkan invoice & DO.
- Yang diharapkan: kartu menampilkan progres per peran; item yang belum dicentang admin (poster) terlihat jelas.
- Kalau salah: item gratis terlupa sampai ada komplain.

**Skenario 3 — Editing (dari board E)**

- Situasi: 2 kartu di Siap Edit; editor Abeng, Akew, Ardy, Bima, Caesar, Rafi.
- Yang dilakukan: SPV menyeret kartu ke kolom Abeng, atau Abeng mengambilnya sendiri.
- Yang diharapkan: kartu tercatat milik Abeng; path folder & redaksi tersedia di kartu; item tanpa kode desain ditandai "menunggu kode".
- Kalau salah: editor mengerjakan sekolah yang belum punya kode desain.

---

## 5. Perubahan data

| Data baru | Dari mana | Data lama ikut berubah? |
| --------- | --------- | ----------------------- |
| Order induk pada order susulan | Dipilih saat membuat susulan | Tidak |
| Centang QC per item per peran (+ siapa & kapan) | Tim event, admin collection | Tidak; order lama mulai tanpa centang |
| Redaksi per order + tanda pakai/tanpa redaksi per item | Otomatis dari data sekolah, bisa dikoreksi | Tidak |
| Tahap, penanggung jawab, tenggat, tanda tertahan per kartu | Papan kanban | 🔍 Order yang sudah berjalan perlu ditempatkan ke tahap awal yang benar |
| Kelengkapan berkas (INV, INV+PIC, DO, video pembayaran) | Unggahan admin collection | Tidak |

---

## 6. TIDAK termasuk Fase 2

- Menyimpan foto hasil event di sistem — tetap di server kantor (`\\delapanmataair\...`); sistem hanya membuat path-nya.
- Pembaruan papan seketika (websocket) — cukup penyegaran berkala.
- Kolom papan yang bisa ditambah pengguna, checklist bebas, label warna bebas.
- Cover bergambar pada kartu.
- 🔍 Data per siswa di luar yearbook (menunggu konfirmasi).

---

## 7. Alur baru yang perlu digambar

| Alur | Peran yang terlibat | Sudah digambar? |
| ---- | ------------------- | --------------- |
| Perjalanan kartu tahap C → P | admin collection, editor, QC marketing, produksi, delivery, finance | Belum — menunggu board F–P |
| Order susulan | marketing, admin, tim event | Belum |

### Pemetaan board Trello ke sistem

Pola Trello mereka: **board = tahap, list = orang yang memegang, kartu = order.**
Angka desimal pada nama board adalah cabang dari tahap yang sama, dipecah karena
empat alasan — jenis produk, orang/PIC, wilayah, atau status tahan.

| Tahap | Board sekarang | Di sistem |
|---|---|---|
| A | 1 Order, 1.1 H-7, 1.2 H-2 | ✅ Sudah ada di Fase 1 |
| B | 2 Team Event (H+0), 2.1 Konfirmasi H+1 | ✅ Sebagian besar ada; H+1 belum |
| C | 3 Collect Admin (H+1) | Kelengkapan berkas + seleksi foto (admin collection); list per wilayah = filter cabang |
| D | 4 Hold piutang/lunas/khusus, 4.2 Cancel, 4.3 Data yearbook | Tanda tertahan + batal (sudah ada); data yearbook = jalur YB |
| E | 5 Editing, 5.1 Susulan, 5.2 YB, 5.3 Revisi | Siap Edit → kolom per editor; list tanggal = tenggat opsional |
| F | 6 QC marketing (H+3), 6.1 QC YB, 6.2 Naik cetak YB | 🔍 |
| G | 7 Produksi reguler / souvenir (H+4) / YB / box YB | 🔍 dipecah per jalur produk |
| H | 8 Cetul | 🔍 ❓ cetak ulang? |
| I–J | 9 Hold delivery, 10 Delivery per wilayah | 🔍 wilayah = cabang |
| K–L | 11 Piutang, 12 Validasi lunas per PIC, 12.2 Lunas | Sebagian ada di modul Finance |
| M | 13 Kirim file per PIC | 🔍 PIC = penanggung jawab |
| N | 14 Validasi finance per PIC | Nyambung ke Finance |
| O | 15 Customer complaint | Tanda, bukan tahap berurutan |
| P | 16 Done order | 🔍 |

### Yang sudah dipastikan dari kartu Trello

- **Board A:** isi kartu (data sekolah, harga, tim event, jadwal, item) sudah seluruhnya ada di sistem, kecuali tema yearbook dan prioritas.
- **Checklist di kartu** = QC: marketing menulis item, tim event mengonfirmasi di Hari-H, admin mengonfirmasi lagi di H+1.
- **Kode desain per item** diketik ulang di komentar, padahal sudah tersimpan di sistem.
- **Redaksi** = nama lengkap sekolah + alamat sekolah; "TANPA REDAKSI" berlaku per item.
- **Susulan** = order baru (kesepakatan Fase 1), dibuat marketing, harga sama, langsung ke event.
- **Tenggat per tanggal** di board Editing hanya dipakai saat dikejar deadline; normalnya langsung ke nama editor.
- **Proses seleksi** di board C dilakukan admin collection.

---

## 8. Jadwal & titik tinjau

| Tahap | Isi | Target | Ditinjau oleh |
| ----- | --- | ------ | ------------- |
| 1 | Order susulan | | |
| 2 | QC item per peran | | |
| 3 | Redaksi & path folder otomatis | | |
| 4 | Mockup papan kanban (desktop + HP) | | |
| 5 | Mesin papan + tahap C & E | | |
| 6 | Pindah panel staf ke subdomain | | |
| 7 | Tahap F–P (bertahap, per board) | | |

---

## 9. Cara revisi masuk

Disepakati di awal, bukan saat sudah ramai.

- **Kanal:** (satu saja)
- **Format wajib:** apa yang diubah · kenapa · contoh kasus · siapa yang terdampak
- **Yang memutuskan bila ada beda pendapat:**
- **Revisi di luar ruang lingkup:** masuk daftar Fase 3, tidak disisipkan

---

## 10. Operasional

| Hal | Penanggung jawab | Catatan |
| --- | ---------------- | ------- |
| Cadangan database | | pernah dicoba dipulihkan? |
| Perpanjangan domain | | situs sempat mati sehari karena verifikasi domain terlewat |
| Token WhatsApp (Fonnte) | | |
| Sertifikat HTTPS | | ditambah sertifikat untuk subdomain panel staf |
| DNS subdomain panel staf | | ❓ nama subdomain (usulan: `app.8mataair.com`) |
| Pengaturan session untuk subdomain | | diubah di `.env` server |
| Kapasitas server | | cek `free -m` & `docker stats` sebelum papan dibangun; RAM paling perlu dijaga |
| Jendela deploy & siapa yang boleh | | |
| Tempat uji coba sebelum produksi | | belum ada — bug Fase 1 ketahuan dari pengguna |

---

## 11. Utang teknis Fase 1 yang dibawa

| Hal | Dampak bila dibiarkan |
| --- | --------------------- |
| Responsif mobile dashboard staf | Pernah disebut prioritas pemilik, belum dikerjakan; papan kanban di HP butuh tombol "Pindah ke", bukan seret |
| Multi cabang belum dipakai sungguhan | Perilaku sudah setara untuk data sekarang, tapi belum teruji dengan pengguna dua cabang |
| Kolom cabang lama belum dipindah penuh | Sengaja ditahan agar pembatas data antar cabang tidak dibongkar sekaligus |
| Teks "DP harus disetujui dulu sebelum H-7" | Sisa aturan lama yang sudah tidak berlaku; tidak tampil, tapi membingungkan saat membaca kode |
| Syarat H-2 tersebar di 5 tempat (Hari-H, STE, progres, filter, tracking) | Semuanya harus mengenali order susulan |
| Belum ada tempat uji coba | Bug ketahuan di produksi, dari laporan pengguna |

---

## 12. Pertanyaan terbuka untuk DMA

| # | Pertanyaan | Mempengaruhi |
| - | ---------- | ------------ |
| 1 | Siapa yang menugaskan tim event untuk order susulan? | Hak akses susulan |
| 2 | Produk susulan yang tidak ada di order induk memakai harga katalog? | Aturan uang susulan |
| 3 | Order susulan perlu ditandai di API Report Order? | Laporan web report |
| 4 | Data per siswa hanya untuk yearbook, atau juga untuk daftar paket A/B seperti di TK Miftahul Khoir? | Besar pekerjaan |
| 5 | ID sekolah 8 digit di DB mereka itu NPSN? Sekolah baru didaftarkan di web report atau di DMA? | Item API sekolah (tertahan) |
| 6 | Nama & alamat di web report sudah nama resmi yang layak cetak? | Redaksi otomatis |
| 7 | Editor boleh memindahkan kartu ke editor lain? | Hak akses papan |
| 8 | Tema yearbook & prioritas perlu jadi isian order? | Isian order |
| 9 | Isi board F sampai P (tampilan list + satu kartu dibuka) | Tahap F–P |
| 10 | Nama subdomain panel staf | DNS & SSL |
