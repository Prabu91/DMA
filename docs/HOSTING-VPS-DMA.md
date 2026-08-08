# Kebutuhan Server & Domain — DMA (untuk diskusi dengan DMA)

_Disusun 2026-08-01. Harga per Agustus 2026 — konfirmasi ulang saat pembelian (harga bisa berubah)._

Aplikasi DMA = **Laravel 13 + Livewire + PostgreSQL + PDF (DomPDF) + upload gambar + email**.
Pengguna: staf internal (multi-cabang) + storefront sekolah (musiman, ramai saat musim foto sekolah).

---

## 1. Spesifikasi server yang direkomendasikan

| | Spek | Untuk |
|---|---|---|
| **Rekomendasi produksi** | **2 vCPU · 4–8 GB RAM · 80–100 GB NVMe SSD · Ubuntu 22.04/24.04 LTS** | Operasional + storefront lancar, ada ruang tumbuh |
| Hemat (awal) | 2 vCPU · 2 GB RAM · 40 GB SSD | Cukup untuk mulai, upgrade nanti |
| Kalau ramai | 4 vCPU · 8 GB RAM | Storefront makin padat |

Catatan: gambar produk/desain menumpuk seiring waktu → pilih disk ≥ 80 GB (atau tambah object storage nanti).

---

## 2. Perbandingan detail: Jagoan Galaxy vs Hostinger KVM 2

| | **Jagoan Galaxy** | **Hostinger KVM 2** |
|---|---|---|
| CPU | **4 vCPU** | 2 vCPU (AMD EPYC) |
| RAM | 4 GB | **8 GB** |
| Storage | 100 GB (SSD/NVMe) + **100 GB backup** | 100 GB **NVMe** |
| Bandwidth | **Unmetered**, up to 10 Gbps | 8 TB, 1 Gbps |
| IP publik | 1 IPv4 gratis | 1 IPv4 dedicated gratis |
| Root access | ✅ | ✅ |
| Backup | 100 GB storage backup | Mingguan otomatis + snapshot manual |
| Keamanan | Firewall + DDoS + HA Cluster | Firewall + DDoS + **malware scanner** |
| Control panel | CloudPanel/Webmin gratis (cPanel berbayar) | **hPanel** bawaan + AI terminal |
| OS | Ubuntu/Debian/AlmaLinux/Rocky + PostgreSQL | Ubuntu dll + template |
| Lokasi server | **Indonesia (lokal)** | Global — **pilih region Jakarta** saat checkout |
| Support | 24/7 **WhatsApp lokal** | 24/7 live chat (AI dulu, baru manusia) |
| Uptime | 99,9% | 99,9% |
| Managed? | Unmanaged | Unmanaged |
| Domain gratis thn 1 | **.cloud** (kurang ideal utk bisnis) | **.com** dll (lebih berguna) |
| Harga | **Rp200.000/bln (flat, tanpa komitmen)** | Promo Rp151.900/bln (**wajib 24 bln di muka**), renewal **Rp232.900/bln** |

---

## 3. SSL & keperluan lain (di luar VPS)

| Kebutuhan | Jagoan | Hostinger | Catatan |
|---|---|---|---|
| **SSL/HTTPS** | **Gratis** (Let's Encrypt) | **Gratis** (Let's Encrypt) | Di VPS, SSL **tidak perlu beli** — pasang Let's Encrypt (via panel/certbot), auto-perpanjang. **Rp0 di keduanya.** |
| Control panel | CloudPanel gratis | hPanel bawaan | Laravel **tak wajib cPanel**. |
| Kirim email (verifikasi + OTP) | Layanan transaksional | Sama | Pakai **Brevo/Resend tier gratis** → **Rp0**. SMTP langsung dari VPS rawan masuk spam. |
| Deploy & maintenance | Perlu tenaga teknis | Perlu tenaga teknis | **Keduanya unmanaged** → setup/update/keamanan dikerjakan tim. Opsional tool deploy (Ploi/RunCloud ~$8–15/bln). |
| Backup | Termasuk 100 GB | Termasuk (mingguan) | Aktifkan & cek rutin. |

---

## 4. Harga domain (per tahun) + perpanjangan

| Ekstensi | Daftar (tahun 1) | **Perpanjangan/tahun** |
|---|---|---|
| .com | Rp150.000–185.000 | Rp150.000–200.000 |
| .id | Rp120.000–200.000 | Rp180.000–250.000 |
| .co.id (butuh dok. badan hukum) | Rp200.000–250.000 | ~Rp200.000–300.000 |

⚠️ **Awas promo:** banyak registrar pasang tahun 1 Rp15rb–50rb tapi **renewal Rp200rb–400rb**. Cek harga renewal, bukan harga daftar.

---

## 5. Estimasi total biaya (VPS + domain .com)

| Opsi | Tahun 1–2 | Perpanjangan/tahun | Total ~3 tahun | Bayar di muka |
|---|---|---|---|---|
| **Hostinger KVM 2** (2C/**8GB**) | ~Rp1,82 jt/thn | ~Rp2,79 jt/thn | **~Rp6,8 jt** | ~Rp3,65 jt (24 bln) |
| **Jagoan Galaxy** (4C/4GB) | Rp2,4 jt/thn (rata) | Rp2,4 jt/thn | **~Rp7,8 jt** | Bebas (bulanan/tahunan) |
| Hetzner CX22 (2C/4GB, luar negeri) | ~Rp0,9 jt/thn | ~Rp0,9 jt/thn | ~Rp2,7 jt | Kartu kredit |

---

## 6. Rekomendasi

- **Kalau DMA sanggup bayar 2 tahun di muka & mau spek/RAM terbaik + hemat** → **Hostinger KVM 2** (8 GB RAM = keunggulan nyata untuk Laravel + PostgreSQL). Pilih **region Jakarta**.
- **Kalau mau support & server lokal, latensi rendah, bayar fleksibel tanpa komitmen & tanpa kejutan renewal** → **Jagoan Galaxy**.
- **Kalau anggaran sangat ketat & oke server luar negeri** → Hetzner (~Rp0,9 jt/tahun).

Ketiganya penyedia terpercaya (Hostinger Trustpilot 4,6–4,7/5; Jagoan mapan lokal; Hetzner reputasi global). Untuk Hostinger, yang perlu dijaga bukan kepercayaannya, tapi **kelola tagihan/renewal** (hitung total 2–3 tahun, matikan upsell).

---

## 7. Biaya API peta (untuk fitur estimasi perjalanan — bila jadi dibangun)

Volume rendah (±1 panggilan per event selesai) → **kemungkinan besar gratis**:

| Event/bulan | Tanpa kuota gratis | Dengan kuota gratis |
|---|---|---|
| 500 | ~Rp40rb–80rb | **Rp0** |
| 1.000 | ~Rp80rb–160rb | Rp0 / sangat kecil |
| 3.000 | ~Rp240rb–480rb | kecil |

Alternatif bisa **Rp0 total**: Mapbox (100rb gratis/bln), OpenRouteService, atau precompute jarak sekolah↔kantor. **Biaya API bukan penghambat** — yang berat: keputusan platform (perlu app native untuk GPS?) + kebijakan privasi lokasi karyawan.

---

## 8. Yang perlu diputuskan bersama DMA (fitur akhir flow: GPS + folder event)

**Tujuan**
- Tracking perjalanan pulang untuk apa? (absensi/jam kerja? keamanan? penilaian kinerja?) → menentukan seberapa akurat & ketat.

**Platform & GPS**
- Tim event pakai HP pribadi? Cukup browser atau perlu **aplikasi terpasang**?
- GPS ditandai **sekali** (selesai event + sampai kantor) atau **terus-menerus**? (terus-menerus = wajib app native)
- Koordinat GPS kantor tiap cabang?
- Kalau tim tidak langsung ke kantor, dihitung bagaimana?

**Estimasi & notifikasi**
- Estimasi durasi perlu **API peta** — DMA siap? Atau cukup estimasi kasar?
- "Pemberitahuan segera kembali" via push HP / WhatsApp / email / dalam app?

**Privasi**
- Karyawan **setuju** lokasinya dilacak? Ada kebijakan/consent tertulis?

**Folder event (download)**
- Isinya cuma struktur folder + note kode desain, atau nanti diisi file foto?
- Format ZIP? Penamaan persis seperti apa? (`10RP_WISUDA_MINIMALIS` = nama folder atau isi note? pola `{ukuran}_{tema}_{gaya}` atau kode desain WIS-01?)
- Item FREE ikut dibuatkan folder?
- Siapa & kapan yang download?
