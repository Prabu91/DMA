<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email OTP penyelesaian event ke guru/PIC sekolah. Guru membacakan kode ini
 * ke tim event sebagai bukti event benar selesai.
 */
class EventOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $otp,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode OTP Penyelesaian Event — '.($this->order->booking_code ?? 'Order #'.$this->order->id),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.event-otp',
        );
    }
}
