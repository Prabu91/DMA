<?php

namespace App\Notifications;

use App\Models\Kanban\Kartu;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Satu kabar kanban: tampil di lonceng dan (kecuali dimatikan) dikirim email,
 * meniru pemberitahuan Trello.
 */
class KanbanKabar extends Notification
{
    public const SEBUT = 'sebut';

    public const KOMENTAR = 'komentar';

    public const DITUGASKAN = 'ditugaskan';

    public const TENGGAT = 'tenggat';

    public const TENGGAT_DIUBAH = 'tenggat_diubah';

    public const KARTU_PINDAH = 'kartu_pindah';

    public const KARTU_DIARSIPKAN = 'kartu_diarsipkan';

    public function __construct(
        public string $jenis,
        public Kartu $kartu,
        public ?User $oleh = null,
        public ?string $cuplikan = null,
        public bool $email = true,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->email && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    /** Kalimat kabar, mis. "Faris menyebut Anda di kartu SD Harapan". */
    public function kalimat(): string
    {
        $siapa = $this->oleh?->nama ?? $this->oleh?->name ?? 'Seseorang';

        return match ($this->jenis) {
            self::SEBUT => $siapa.' menyebut Anda',
            self::KOMENTAR => $siapa.' berkomentar',
            self::DITUGASKAN => $siapa.' menugaskan Anda',
            self::TENGGAT => 'Tenggat sudah dekat',
            self::TENGGAT_DIUBAH => $siapa.' mengubah tenggat',
            self::KARTU_PINDAH => $siapa.' memindahkan kartu',
            self::KARTU_DIARSIPKAN => $siapa.' mengarsipkan kartu',
            default => 'Ada perubahan',
        };
    }

    public function tautan(): string
    {
        $dasar = rtrim((string) (config('kanban.domain') ? 'https://'.config('kanban.domain') : config('app.url')), '/');

        return $dasar.'/kanban/b/'.$this->kartu->board_id.'?kartu='.$this->kartu->id;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'jenis' => $this->jenis,
            'kartu_id' => $this->kartu->id,
            'board_id' => $this->kartu->board_id,
            'kartu' => $this->kartu->judul,
            'board' => $this->kartu->board?->nama,
            'oleh' => $this->oleh?->nama ?? $this->oleh?->name,
            'oleh_id' => $this->oleh?->id,
            'kalimat' => $this->kalimat(),
            'cuplikan' => $this->cuplikan ? mb_substr($this->cuplikan, 0, 300) : null,
            'tautan' => $this->tautan(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pesan = (new MailMessage)
            ->subject($this->kalimat().' di kartu "'.$this->kartu->judul.'"')
            ->greeting('Halo '.($notifiable->nama ?? $notifiable->name).',')
            ->line($this->kalimat().' di kartu **'.$this->kartu->judul.'** (board '.($this->kartu->board?->nama ?? '-').').');

        if ($this->cuplikan) {
            $pesan->line('> '.mb_substr($this->cuplikan, 0, 500));
        }

        return $pesan
            ->action('Buka kartu', $this->tautan())
            ->line('Anda menerima email ini karena mengikuti kartu tersebut.');
    }
}
