# PRD Fase 2 — DMA

> Kerangka untuk diisi bersama DMA. Contoh yang ada di tiap bagian diambil dari
> kasus nyata Fase 1 — hapus setelah bagian itu terisi.
>
> Aturan main: **setiap fitur wajib punya contoh dengan angka/nama sungguhan.**
> Kalau contohnya belum bisa dibuat, kebutuhannya belum matang — tandai sebagai
> "perlu digali", jangan dimasukkan ke ruang lingkup.

| | |
| --- | --- |
| Versi | draf 1 |
| Tanggal | |
| Disusun | Faris |
| Disetujui | (nama + tanggal) |

---

## 1. Tujuan Fase 2

Satu paragraf: masalah apa yang mau diselesaikan, bukan fitur apa yang mau dibuat.

**Ukuran keberhasilan** — maksimal 3, harus bisa diukur:

1.
2.
3.

---

## 2. Ruang lingkup

Diurutkan dari yang paling merugikan kalau tidak ada. Kalau semuanya "penting",
belum diurutkan.

| # | Fitur | Kenapa perlu | Siapa yang memakai | Definisi selesai |
| - | ----- | ------------ | ------------------ | ---------------- |
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |

> Contoh pengisian:
> | 1 | Responsif mobile dashboard staf | Marketing bekerja dari HP di lapangan, sekarang tabelnya meluber | marketing, tim event | Semua halaman staf terbaca di layar 375px tanpa geser ke samping |

---

## 3. Aturan bisnis

Bagian paling penting. Ini yang bikin bongkar ulang kalau dilewat.

### 3.1 Aturan uang

Tulis sebagai **contoh perhitungan**, bukan deskripsi.

| Skenario | Masukan | Hasil yang benar |
| -------- | ------- | ---------------- |
| | | |

> Contoh dari Fase 1:
> | Yearbook + box | 60 HALAMAN (Rp320.000, mengganti) + BOX (Rp40.000, menambah) | Rp360.000 |
> | Pas foto | 20 pcs dibagi 2x3 (4) + 3x4 (2), harga paket Rp20.000 | Rp20.000 — komposisi tidak mengubah harga |

### 3.2 Aturan hak akses

| Peran | Boleh | Tidak boleh |
| ----- | ----- | ----------- |
| | | |

> Perhatikan khusus: apa pun yang lintas cabang. Salah di sini berarti data satu
> cabang terlihat oleh cabang lain.

### 3.3 Aturan data

Apa yang tidak boleh diubah setelah tahap tertentu, dan kenapa.

| Data | Terkunci sejak | Alasan |
| ---- | -------------- | ------ |
| | | |

---

## 4. Skenario pemakaian

Tulis dari sudut pandang orang yang memakai, dengan nama dan angka sungguhan.

**Skenario 1 —**

- Situasi:
- Yang dilakukan:
- Yang diharapkan terjadi:
- Kalau salah/gagal:

> Contoh:
> - Situasi: Rina (marketing, pegang cabang Bandung & Cimahi) membuat order untuk TK Al-Luthfi di Cimahi
> - Yang dilakukan: pilih sekolah, pilih cabang Cimahi, simpan
> - Yang diharapkan: order masuk kotak masuk Cimahi dan otomatis ter-assign ke Rina
> - Kalau salah: order nyangkut tanpa penanggung jawab

---

## 5. Perubahan data

Apa yang baru mulai disimpan sistem, dan apakah data lama terpengaruh.

| Data baru | Dari mana | Data lama ikut berubah? |
| --------- | --------- | ----------------------- |
| | | |

---

## 6. TIDAK termasuk Fase 2

Ditulis eksplisit. Ini bagian termurah untuk mencegah pekerjaan melebar.

-
-
-

---

## 7. Alur baru yang perlu digambar

Flowchart HANYA untuk alur yang berpindah tangan antar peran atau punya banyak
status. Kalau Fase 2 tidak menambah alur semacam itu, bagian ini dikosongkan.

| Alur | Peran yang terlibat | Sudah digambar? |
| ---- | ------------------- | --------------- |
| | | |

---

## 8. Jadwal & titik tinjau

Titik tinjau = kapan DMA melihat hasil setengah jadi. Tanpa ini, salah tafsir
baru ketahuan di akhir.

| Tahap | Isi | Target | Ditinjau oleh |
| ----- | --- | ------ | ------------- |
| | | | |

---

## 9. Cara revisi masuk

Disepakati di awal, bukan saat sudah ramai.

- **Kanal:** (satu saja)
- **Format wajib:** apa yang diubah · kenapa · contoh kasus · siapa yang terdampak
- **Yang memutuskan bila ada beda pendapat:**
- **Revisi di luar ruang lingkup:** masuk daftar Fase 3, tidak disisipkan

---

## 10. Operasional

Di luar fitur, tapi Fase 1 membuktikan ini berisiko.

| Hal | Penanggung jawab | Catatan |
| --- | ---------------- | ------- |
| Cadangan database | | pernah dicoba dipulihkan? |
| Perpanjangan domain | | situs sempat mati sehari karena verifikasi domain terlewat |
| Token WhatsApp (Fonnte) | | |
| Sertifikat HTTPS | | |
| Jendela deploy & siapa yang boleh | | |
| Tempat uji coba sebelum produksi | | belum ada — bug Fase 1 ketahuan dari pengguna |

---

## 11. Utang teknis Fase 1 yang dibawa

| Hal | Dampak bila dibiarkan |
| --- | --------------------- |
| Responsif mobile dashboard staf | Pernah disebut prioritas pemilik, belum dikerjakan |
| Multi cabang belum dipakai sungguhan | Perilaku sudah setara untuk data sekarang, tapi belum teruji dengan pengguna dua cabang |
| Kolom cabang lama belum dipindah penuh | Sengaja ditahan agar pembatas data antar cabang tidak dibongkar sekaligus |
| Menu Desain masih disembunyikan | Keputusan tertunda |
| Belum ada tempat uji coba | Bug ketahuan di produksi, dari laporan pengguna |
