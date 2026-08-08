<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #20222b; font-size: 12px; margin: 0; }
        .muted { color: #7A7C86; }
        .head { width: 100%; border-bottom: 3px solid #2E3192; padding-bottom: 10px; margin-bottom: 6px; }
        .head td { vertical-align: top; }
        .brand { font-size: 18px; font-weight: bold; color: #2E3192; }
        .doc { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .code { font-size: 14px; font-weight: bold; letter-spacing: 1px; }
        .title { font-size: 12px; font-weight: bold; margin: 14px 0 4px; color: #2E3192; text-transform: uppercase; letter-spacing: .04em; }
        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { padding: 3px 0; vertical-align: top; }
        table.kv td.k { width: 130px; color: #7A7C86; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.items th { text-align: left; background: #f4f4f6; border-bottom: 1px solid #E0E0E4; padding: 6px 8px; font-size: 11px; }
        table.items td { border-bottom: 1px solid #E0E0E4; padding: 6px 8px; }
        .right { text-align: right; }
        .box { border: 1px solid #E0E0E4; border-radius: 6px; padding: 10px 12px; }
        .tim td { padding: 5px 8px; border-bottom: 1px solid #E0E0E4; }
        .sign { margin-top: 40px; width: 100%; }
        .sign td { width: 50%; vertical-align: top; text-align: center; }
        .sign .line { margin-top: 55px; border-top: 1px solid #20222b; padding-top: 4px; display: inline-block; min-width: 160px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="brand">DMA · Delapan Mata Air</div>
                <div class="muted">Studio Foto · Cabang {{ $order->cabang?->nama }}</div>
                <div class="doc" style="margin-top:8px">SURAT TUGAS EVENT</div>
            </td>
            <td class="right">
                @if ($order->booking_code)
                    <div class="muted">Kode booking</div>
                    <div class="code">{{ $order->booking_code }}</div>
                @else
                    <div class="muted">Order #{{ $order->id }}</div>
                @endif
                <div class="muted" style="margin-top:6px">Dicetak {{ now()->translatedFormat('d M Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table style="width:100%; margin-top:8px">
        <tr>
            <td style="width:50%; vertical-align:top; padding-right:12px">
                <div class="title">Sekolah</div>
                <table class="kv">
                    <tr><td class="k">Nama</td><td>{{ $order->sekolah?->nama }}</td></tr>
                    <tr><td class="k">ID Sekolah</td><td>{{ $order->sekolah?->id_sekolah }}</td></tr>
                    @if ($order->sekolah?->alamat)<tr><td class="k">Alamat</td><td>{{ $order->sekolah->alamat }}</td></tr>@endif
                    @if ($order->sekolah?->kota)<tr><td class="k">Kota</td><td>{{ $order->sekolah->kota }}</td></tr>@endif
                    @if ($order->sekolah?->pic_sekolah)<tr><td class="k">PIC</td><td>{{ $order->sekolah->pic_sekolah }}</td></tr>@endif
                    @if ($order->sekolah?->no_telp_pic)<tr><td class="k">No. telepon</td><td>{{ $order->sekolah->no_telp_pic }}</td></tr>@endif
                    @php $emailGuru = $order->sekolah?->email ?: $order->sekolah?->email_guru; @endphp
                    @if ($emailGuru)<tr><td class="k">Email guru</td><td>{{ $emailGuru }}</td></tr>@endif
                </table>
            </td>
            <td style="width:50%; vertical-align:top">
                <div class="title">Jadwal &amp; penanggung jawab</div>
                <table class="kv">
                    <tr><td class="k">Tanggal event</td><td><b>{{ $order->tanggal_event ? $order->tanggal_event->translatedFormat('l, d M Y') : 'Belum ditentukan' }}</b></td></tr>
                    <tr><td class="k">Jam</td><td>{{ $order->jam_event ?: '—' }}</td></tr>
                    <tr><td class="k">Jumlah siswa</td><td>{{ $order->jumlah_siswa }}</td></tr>
                    <tr><td class="k">Marketing</td><td>{{ $order->marketing?->nama ?? $order->marketing?->name ?? '—' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="title">Tim event yang ditugaskan</div>
    @if ($order->timEvent->isEmpty())
        <div class="box muted">Belum ada tim event yang ditugaskan.</div>
    @else
        <table class="items">
            <thead><tr><th style="width:30px">No</th><th>Nama</th></tr></thead>
            <tbody>
                @foreach ($order->timEvent as $i => $anggota)
                    <tr class="tim"><td>{{ $i + 1 }}</td><td>{{ $anggota->nama ?? $anggota->name }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="title">Rincian pesanan</div>
    <table class="items">
        <thead><tr><th>Item</th><th class="right">Qty</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->produk?->nama ?? $item->paket?->nama }}@if ($item->is_free) <span class="muted">(gratis)</span>@endif</td>
                    <td class="right">{{ $item->qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign">
        <tr>
            <td>Marketing<br><span class="line">{{ $order->marketing?->nama ?? $order->marketing?->name ?? '(…)' }}</span></td>
            <td>Koordinator Tim Event<br><span class="line">(…)</span></td>
        </tr>
    </table>

    <p class="muted" style="margin-top:24px; font-size:10px">
        Surat Tugas Event ini diterbitkan otomatis oleh sistem DMA sebagai penugasan pelaksanaan sesi foto sekolah.
    </p>
</body>
</html>
