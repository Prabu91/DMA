# API Report Order — DMA

Baca detail order & realisasi nominal omset dari sistem DMA, untuk dipakai
aplikasi web report di luar. Angkanya diambil dari query yang sama persis
dengan halaman **Report order** di panel DMA, jadi totalnya tidak akan beda.

API ini **hanya baca** — tidak bisa mengubah data apa pun.

**Base URL:** `https://8mataair.com/api/v1`

---

## Pemasangan

### 1. Minta token ke admin DMA

Admin membuka **Pengaturan → API report order** di panel, menekan **Buat token**,
lalu menyalin token yang muncul. Token hanya tampil sekali; setelah halaman
ditutup tidak bisa dilihat lagi.

### 2. Simpan token di luar folder publik

Taruh di file konfigurasi yang tidak bisa dibuka lewat URL — jangan di dalam
`public_html`, dan jangan pernah di JavaScript. Siapa pun yang punya token ini
bisa membaca seluruh data omset.

### 3. Panggil dengan header Authorization

```bash
curl -H "Authorization: Bearer <TOKEN>" \
  "https://8mataair.com/api/v1/report-order/ringkasan"
```

---

## Endpoint

### `GET /report-order/ringkasan`

Total baris, qty, dan nominal saja. Ringan — ini yang dipakai untuk angka omset
yang menyegar terus.

```json
{
  "filter": { "dari": "2026-09-01", "sampai": "2026-09-30" },
  "ringkasan": {
    "baris": 41,
    "qty": 20,
    "nominal": 3200000
  },
  "diambil_pada": "2026-09-07T21:37:51+07:00"
}
```

### `GET /report-order`

Baris detail per item order, berhalaman. Satu order dengan 4 produk = 4 baris.
Balasannya juga memuat `ringkasan` yang sama seperti di atas.

```json
{
  "data": [
    {
      "order_item_id": 20,
      "booking_code": "070926BDG00001",
      "tanggal_booking": "2026-09-07 10:04:40",
      "order_status": "dp",
      "order_dihapus": false,
      "marketing": "Rudi Setiawan",
      "sekolah": {
        "id_sekolah": "SKL-000028",
        "nama": "TK IP AL-LUTHFI 2",
        "alamat": "Komplek Girimekar Permai"
      },
      "item": {
        "nama": "Yearbook",
        "tipe": "produk",
        "opsi": "60 HALAMAN · BOX",
        "is_free": false
      },
      "qty": 20,
      "harga": 160000,
      "diskon": 0,
      "nominal": 3200000
    }
  ],
  "meta": {
    "halaman": 1,
    "per_halaman": 50,
    "total_baris": 41,
    "total_halaman": 1
  }
}
```

---

## Filter

Semua opsional, dipakai sebagai query string di kedua endpoint.

| Parameter    | Isi                                                        |
| ------------ | ---------------------------------------------------------- |
| `dari`       | Tanggal booking mulai — `2026-09-01`                        |
| `sampai`     | Tanggal booking sampai — `2026-09-30`                       |
| `cabang_id`  | Batasi ke satu cabang (angka)                               |
| `produk_id`  | Batasi ke satu produk (angka)                               |
| `jenis`      | `berbayar` atau `free`                                      |
| `q`          | Cari nama produk / sekolah / ID sekolah / kode booking      |
| `per_page`   | Baris per halaman, maks 200 (bawaan 50)                     |
| `page`       | Halaman ke berapa                                           |

---

## Cara hitung nominal

Ini bagian yang paling sering jadi selisih, jadi perlu disamakan sejak awal.

| Hal              | Aturannya                                                                                                                                                                        |
| ---------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `nominal`        | `(harga − diskon) × qty` per baris. Sudah dihitungkan, tidak perlu dihitung ulang.                                                                                                |
| `order_dihapus`  | Order yang dibuang ke sampah **tetap muncul** dan ditandai `true`, tapi **tidak ikut** dijumlahkan di `ringkasan`. Supaya sama dengan panel DMA, pakai `ringkasan` apa adanya — jangan menjumlah sendiri dari `data`. |
| `is_free`        | Item bonus. Nominalnya 0 dan tetap ikut sebagai baris.                                                                                                                            |
| —                | Hanya order yang **sudah ditugaskan ke marketing** yang masuk laporan.                                                                                                            |

---

## Catatan untuk shared hosting

API-nya ada di server DMA; hosting kamu cuma perlu memanggil keluar lewat HTTPS
biasa. Karena pengamanannya pakai token (bukan daftar IP), **IP hosting yang
berubah-ubah tidak jadi masalah.**

Empat hal yang tetap perlu dicek:

- **Panggilan keluar diizinkan?**
  Sebagian shared hosting mematikan cURL atau memaksa lewat proxy. Ini
  satu-satunya hal yang bisa bikin gagal total, dan paling gampang dites duluan —
  jalankan contoh kode di bawah. Kalau tertutup, minta hosting membukanya.

- **Batas waktu eksekusi (sering 30 detik).**
  Jangan tarik ribuan baris sekaligus. Untuk angka omset pakai `/ringkasan`;
  untuk detail pakai `per_page` secukupnya (50–100) lalu ambil per halaman.

- **Sertifikat TLS hosting.**
  Kalau muncul *SSL certificate problem*, daftar sertifikat di hosting sudah
  usang — minta hosting memperbaruinya. **Jangan** diakali dengan mematikan
  verifikasi SSL: token bisa disadap di jalan.

- **Cron biasanya minimal 5 menit.**
  Kalau ingin terasa langsung, jangan andalkan cron. Panggil saat halaman report
  dibuka, lalu simpan hasilnya 30–60 detik supaya tidak memanggil berulang kali
  untuk pengunjung yang sama.

**Batas laju:** 120 permintaan per menit per IP; lebih dari itu dibalas `429`.
Polling tiap 30 detik masih jauh dari batas ini — angka tersebut ada untuk
menahan loop yang lepas kendali, bukan membatasi pemakaian wajar.

---

## Contoh kode PHP

Versi paling sederhana yang jalan di kebanyakan shared hosting.

```php
// token disimpan di luar public_html
$token = require '/home/akun/rahasia/dma-token.php';

$ch = curl_init('https://8mataair.com/api/v1/report-order/ringkasan?dari=2026-09-01');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT        => 15,
]);

$body = curl_exec($ch);
$kode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($kode !== 200) {
    // jangan tampilkan $token di pesan error
    throw new RuntimeException("API DMA balas $kode");
}

$data = json_decode($body, true);
echo number_format($data['ringkasan']['nominal'], 0, ',', '.');
// -> 3.200.000
```

---

## Kalau ada masalah

| Balasan | Artinya                                                                                                                        |
| ------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `200`   | Beres.                                                                                                                          |
| `401`   | Token salah, atau header `Authorization` tidak terkirim. Sebagian shared hosting membuang header ini — cek dulu apakah sampai.  |
| `503`   | Token belum dibuat atau sudah dicabut di panel DMA. Minta admin membuat ulang.                                                  |
| `422`   | Ada filter tidak sah — mis. `jenis` diisi selain `berbayar`/`free`, atau tanggal salah format.                                  |
| `429`   | Terlalu sering memanggil. Beri jeda, atau cache hasilnya.                                                                       |

Kalau token bocor atau dicurigai bocor: minta admin DMA menekan **Ganti token**
di halaman Pengaturan. Token lama langsung berhenti berlaku.
