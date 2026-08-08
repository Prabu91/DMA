<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0; background:#f4f4f6; font-family: Arial, Helvetica, sans-serif; color:#20222b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;">
        <tr><td align="center">
            <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e0e0e4;">
                <tr><td style="background:#2E3192; padding:18px 24px;">
                    <div style="color:#ffffff; font-size:18px; font-weight:bold;">DMA · Delapan Mata Air</div>
                </td></tr>
                <tr><td style="padding:24px;">
                    <p style="margin:0 0 6px; font-size:15px;">Yth. {{ $order->sekolah?->pic_sekolah ?? 'Bapak/Ibu Guru' }},</p>
                    <p style="margin:0 0 18px; font-size:14px; color:#555;">
                        Berikut kode OTP untuk menyelesaikan event foto di
                        <strong>{{ $order->sekolah?->nama }}</strong>. Mohon bacakan kode ini
                        kepada tim event di lokasi sebagai konfirmasi event telah selesai.
                    </p>

                    <div style="text-align:center; margin:22px 0;">
                        <div style="font-size:12px; letter-spacing:.08em; color:#7A7C86; text-transform:uppercase;">Kode OTP</div>
                        <div style="font-size:34px; font-weight:bold; letter-spacing:.32em; color:#2E3192; margin-top:6px;">{{ $otp }}</div>
                    </div>

                    <table role="presentation" width="100%" style="font-size:13px; color:#555; border-top:1px solid #e0e0e4; padding-top:12px;">
                        <tr><td style="padding:3px 0; color:#7A7C86;">Kode booking</td><td style="padding:3px 0; text-align:right; font-weight:bold;">{{ $order->booking_code ?? 'Order #'.$order->id }}</td></tr>
                        <tr><td style="padding:3px 0; color:#7A7C86;">Tanggal event</td><td style="padding:3px 0; text-align:right;">{{ $order->tanggal_event ? $order->tanggal_event->translatedFormat('d M Y') : '—' }}</td></tr>
                        <tr><td style="padding:3px 0; color:#7A7C86;">Berlaku hingga</td><td style="padding:3px 0; text-align:right;">{{ $order->otp_expires?->translatedFormat('d M Y H:i') }}</td></tr>
                    </table>

                    <p style="margin:18px 0 0; font-size:12px; color:#9a9aa2;">
                        Jangan bagikan kode ini kepada pihak yang tidak berkepentingan. Jika Anda tidak merasa
                        ada jadwal event, abaikan email ini.
                    </p>
                </td></tr>
                <tr><td style="background:#f4f4f6; padding:14px 24px; font-size:11px; color:#9a9aa2;">
                    © {{ date('Y') }} DMA · Studio Foto Sekolah
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
