@props(['nilai', 'satuan' => null])

{{--
    Harga katalog. Bila saklar "sembunyikan harga dari publik" menyala, harganya
    tidak ditampilkan sama sekali kepada pengunjung yang belum masuk — tanpa teks
    pengganti, karena itu mengotori tata letak kartu & keranjang. Pengunjung
    diarahkan masuk saat menekan "Tambah ke keranjang".
    Satuan (mis. "/ item") dioper lewat prop `satuan` supaya ikut tersembunyi;
    kalau ditulis terpisah di pemanggil, satuannya menggantung tanpa angka.
    Pengguna yang sudah masuk (staf maupun sekolah) selalu melihat harga.
--}}
@if (\App\Support\Pengaturan::bolehLihatHarga())
    <span {{ $attributes }}>Rp{{ number_format((int) $nilai, 0, ',', '.') }}</span>
    @if ($satuan)<span class="text-xs text-ink-muted">{{ $satuan }}</span>@endif
@endif
