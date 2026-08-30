<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #2A2724; font-size: 12px; margin: 0; }
        .muted { color: #7A736B; }
        .head { width: 100%; border-bottom: 2px solid #E08020; padding-bottom: 10px; margin-bottom: 14px; }
        .head td { vertical-align: top; }
        .brand { font-size: 20px; font-weight: bold; color: #E08020; }
        .title { font-size: 13px; font-weight: bold; margin: 14px 0 6px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.items th { text-align: left; background: #FAF8F5; border-bottom: 1px solid #EAE6DF; padding: 6px 8px; font-size: 11px; }
        table.items td { border-bottom: 1px solid #EAE6DF; padding: 6px 8px; }
        .right { text-align: right; }
        .free { color: #16A34A; font-weight: bold; }
        .totrow td { padding: 4px 8px; }
        .grand { font-size: 14px; font-weight: bold; border-top: 2px solid #EAE6DF; }
        .code { font-size: 16px; font-weight: bold; letter-spacing: 1px; }
        .box { border: 1px solid #EAE6DF; border-radius: 6px; padding: 8px; }
        .pending { color: #CA8A04; font-weight: bold; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="brand">DMA</div>
                <div class="muted">Studio Foto · {{ $order->cabang?->nama }}</div>
            </td>
            <td class="right">
                @if ($order->booking_code)
                    <div class="muted">Kode booking</div>
                    <div class="code">{{ $order->booking_code }}</div>
                    <img src="{{ \App\Support\Qr::dataUri(route('storefront.cek', $order->booking_code), 110) }}" width="90" height="90" alt="QR">
                    <div class="muted" style="font-size:7px">Scan untuk verifikasi</div>
                @else
                    <div class="pending">Menunggu penugasan marketing</div>
                    <div class="muted">Booking #{{ $order->id }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table style="width:100%">
        <tr>
            <td style="vertical-align:top; width:50%">
                <div class="title">Sekolah</div>
                <div>{{ $order->sekolah?->nama }}</div>
                <div class="muted">{{ $order->sekolah?->id_sekolah }}</div>
                @if ($order->sekolah?->alamat)<div class="muted">{{ $order->sekolah->alamat }}</div>@endif
                @if ($order->sekolah?->kota)<div class="muted">{{ $order->sekolah->kota }}</div>@endif
                @if ($order->sekolah?->pic_sekolah)<div class="muted">PIC: {{ $order->sekolah->pic_sekolah }}</div>@endif
            </td>
            <td style="vertical-align:top; width:50%">
                <div class="title">Detail</div>
                <div>Tanggal: {{ optional($order->tanggal_booking)->format('d/m/Y H:i') }}</div>
                <div>Jumlah siswa: {{ $order->jumlah_siswa }}</div>
                <div>Marketing: {{ $order->marketing?->nama ?? $order->marketing?->name ?? '—' }}</div>
                <div>Sumber: {{ $order->sumber === 'sekolah' ? 'Booking mandiri sekolah' : 'Dibuat marketing' }}</div>
            </td>
        </tr>
    </table>

    <div class="title">Rincian item</div>
    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th>Desain</th>
                <th>Ukuran</th>
                <th class="right">Qty</th>
                <th class="right">Harga</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>
                        {{ $item->produk?->nama ?? $item->paket?->nama }}
                        @if ($item->is_free)<span class="free"> (GRATIS)</span>@endif
                    </td>
                    <td>{{ $item->desain?->kode ?? '—' }}</td>
                    <td>{{ $item->opsi_ukuran ?? '—' }}</td>
                    <td class="right">{{ $item->qty }}</td>
                    <td class="right">{{ $item->is_free ? 'Rp0' : 'Rp'.number_format($item->harga, 0, ',', '.') }}</td>
                    <td class="right">{{ $item->is_free ? 'Rp0' : 'Rp'.number_format($item->harga * $item->qty, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width:100%; margin-top:10px">
        <tr class="totrow"><td class="right muted">Subtotal</td><td class="right" style="width:140px">Rp{{ number_format($order->total, 0, ',', '.') }}</td></tr>
        <tr class="totrow"><td class="right muted">Item gratis</td><td class="right">{{ $order->items->where('is_free', true)->count() }} item</td></tr>
        <tr class="totrow grand"><td class="right">Total</td><td class="right">Rp{{ number_format($order->total, 0, ',', '.') }}</td></tr>
    </table>

    <p class="muted" style="margin-top:24px; font-size:10px">
        Dokumen ini adalah nota &amp; bukti booking DMA. Tunjukkan kode/QR saat sesi foto.
    </p>
</body>
</html>
