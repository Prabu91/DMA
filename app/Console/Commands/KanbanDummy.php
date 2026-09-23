<?php

namespace App\Console\Commands;

use App\Models\Kanban\Bidang;
use App\Models\Kanban\Board;
use App\Models\User;
use App\Support\Kanban\Akses;
use App\Support\Kanban\Posisi;
use App\Support\Kanban\Warna;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Isi kanban dengan data contoh sebanyak-banyaknya, untuk melihat bagaimana
 * rasanya saat board sudah padat. Semua yang dibuat perintah ini ditandai
 * di kolom deskripsi board, jadi bisa dibuang lagi dengan --hapus.
 */
class KanbanDummy extends Command
{
    protected $signature = 'kanban:dummy
        {--board=8 : Jumlah board contoh}
        {--kartu=5000 : Perkiraan jumlah kartu, dibagi rata ke semua board}
        {--hapus : Buang semua board contoh, jangan membuat yang baru}
        {--force : Jalankan walau di server produksi}';

    protected $description = 'Buat (atau buang) board, list, dan kartu contoh untuk mencoba kanban saat datanya banyak';

    /** Penanda di deskripsi board supaya gampang dibuang lagi. */
    private const TANDA = '[contoh]';

    private const NAMA_BOARD = [
        'A. 1 ORDER', 'A. 2 TEAM EVENT', 'A. 3 EDITING', 'A. 4 CETAK',
        'B. Marketing Jakarta', 'B. Marketing Bandung', 'B. Marketing Surabaya',
        'C. Yearbook 2026', 'C. Wisuda 2026', 'D. Komplain & Revisi',
        'D. Stok & Logistik', 'E. Rapat Internal',
    ];

    private const NAMA_LIST = [
        'Backlog', 'Awaiting deposit', 'Scheduled', 'In progress', 'Proofing',
        'QC', 'Revision', 'Ready to print', 'Shipped', 'Done', 'On hold', 'This month archive',
    ];

    private const SEKOLAH = [
        'SD Harapan Bangsa', 'SMP Tunas Muda', 'TK Miftahul Khoir', 'SMA Cendekia',
        'SDIT Al Fitrah', 'RA Madani', 'SMK Nusantara', 'SD Demo Storefront',
        'MI Al Hafizh', 'SMP Pelita Kasih', 'TK Ceria', 'SDN Sukamaju',
        'SMA Bina Insan', 'SD Islam Assalam', 'SMP Kartika', 'TK Putra Bangsa',
    ];

    private const KOTA = ['Jaksel', 'Jakut', 'Bandung', 'Bekasi', 'Depok', 'Tangsel', 'Bogor', 'Surabaya'];

    private const KEGIATAN = ['Graduation', 'Yearbook', 'Class photos', 'School prewedding', 'Event coverage'];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Ini server produksi. Tambahkan --force kalau memang disengaja.');

            return self::FAILURE;
        }

        return $this->option('hapus') ? $this->hapus() : $this->buat();
    }

    private function hapus(): int
    {
        $board = Board::where('deskripsi', 'like', '%'.self::TANDA.'%')->get();

        if ($board->isEmpty()) {
            $this->info('Tidak ada board contoh.');

            return self::SUCCESS;
        }

        foreach ($board as $b) {
            $b->delete();   // list, kartu, checklist, dan komentarnya ikut terhapus (cascade).
        }

        $this->info('Board contoh dibuang: '.$board->count().'.');

        return self::SUCCESS;
    }

    private function buat(): int
    {
        $pemilik = User::role(Akses::PERAN_STAF)->orderBy('id')->first();

        if (! $pemilik) {
            $this->error('Belum ada pengguna staf. Jalankan db:seed dulu.');

            return self::FAILURE;
        }

        $staf = User::role(Akses::PERAN_STAF)->orderBy('id')->limit(40)->pluck('id')->all();
        $jumlahBoard = max(1, (int) $this->option('board'));
        $targetKartu = max($jumlahBoard, (int) $this->option('kartu'));
        $perBoard = (int) ceil($targetKartu / $jumlahBoard);

        $this->info("Membuat {$jumlahBoard} board contoh, kira-kira {$targetKartu} kartu.");
        $bar = $this->output->createProgressBar($jumlahBoard);
        $bar->start();

        $warnaBoard = array_keys(Warna::BOARD);
        $warnaLabel = array_keys(Warna::LABEL);
        $total = 0;

        for ($i = 0; $i < $jumlahBoard; $i++) {
            $total += $this->buatBoard(
                nama: self::NAMA_BOARD[$i % count(self::NAMA_BOARD)].($i >= count(self::NAMA_BOARD) ? ' '.($i + 1) : ''),
                warna: $warnaBoard[$i % count($warnaBoard)],
                pemilik: $pemilik,
                staf: $staf,
                warnaLabel: $warnaLabel,
                targetKartu: $perBoard,
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Selesai: {$jumlahBoard} board, {$total} kartu.");
        $this->line('Buang lagi dengan: php artisan kanban:dummy --hapus');

        return self::SUCCESS;
    }

    private function buatBoard(string $nama, string $warna, User $pemilik, array $staf, array $warnaLabel, int $targetKartu): int
    {
        $board = Board::create([
            'nama' => $nama,
            'deskripsi' => self::TANDA.' Board contoh untuk mencoba tampilan saat datanya banyak.',
            'warna' => $warna,
            'jenis' => Board::JENIS_BEBAS,
            'visibilitas' => 'workspace',
            'dibuat_oleh' => $pemilik->id,
        ]);

        // Anggota board: sebagian staf, pemiliknya jadi admin.
        $banyak = count($staf);
        $anggota = collect($staf)->shuffle()->take(random_int(min(3, $banyak), min(8, $banyak)))->all();
        $baris = [['board_id' => $board->id, 'user_id' => $pemilik->id, 'peran' => 'admin', 'berbintang' => random_int(0, 3) === 0]];
        foreach (array_diff($anggota, [$pemilik->id]) as $id) {
            $baris[] = ['board_id' => $board->id, 'user_id' => $id, 'peran' => 'anggota', 'berbintang' => false];
        }
        DB::table('kanban_board_anggota')->insert($this->berwaktu($baris));

        // Label: tujuh warna dengan nama yang terpakai sehari-hari.
        $namaLabel = ['Priority', 'Waiting on files', 'Ready to send', 'Revision', 'Follow-up', 'Resend', 'Important'];
        $label = [];
        foreach ($namaLabel as $urut => $teks) {
            $label[] = ['board_id' => $board->id, 'nama' => $teks, 'warna' => $warnaLabel[$urut % count($warnaLabel)]];
        }
        DB::table('kanban_label')->insert($this->berwaktu($label));
        $labelId = DB::table('kanban_label')->where('board_id', $board->id)->pluck('id')->all();

        // Dua bidang khusus supaya lencananya kelihatan di papan.
        $bidangInvoice = Bidang::create(['board_id' => $board->id, 'nama' => 'Invoice no.', 'jenis' => 'teks', 'di_depan' => true, 'posisi' => Posisi::JARAK]);
        Bidang::create([
            'board_id' => $board->id, 'nama' => 'Package type', 'jenis' => 'pilihan',
            'opsi' => ['Basic', 'Medium', 'Premium'], 'di_depan' => false, 'posisi' => Posisi::JARAK * 2,
        ]);

        // List: 6–12 buah, sebagian diberi warna kepala.
        $jumlahList = random_int(6, 12);
        $kolom = [];
        for ($i = 0; $i < $jumlahList; $i++) {
            $kolom[] = [
                'board_id' => $board->id,
                'nama' => self::NAMA_LIST[$i % count(self::NAMA_LIST)],
                'posisi' => ($i + 1) * Posisi::JARAK,
                'warna' => random_int(0, 2) === 0 ? $warnaLabel[$i % count($warnaLabel)] : null,
            ];
        }
        DB::table('kanban_kolom')->insert($this->berwaktu($kolom));
        $kolomId = DB::table('kanban_kolom')->where('board_id', $board->id)->orderBy('posisi')->pluck('id')->all();

        return $this->isiKartu($board, $kolomId, $labelId, $anggota, $pemilik, $bidangInvoice->id, $targetKartu);
    }

    /** Sebar kartu ke list: ada list yang padat, ada yang tipis — seperti aslinya. */
    private function isiKartu(Board $board, array $kolomId, array $labelId, array $anggota, User $pemilik, int $bidangId, int $targetKartu): int
    {
        $bagian = $this->bagiKartu(count($kolomId), $targetKartu);
        $dibuat = 0;

        foreach ($kolomId as $urut => $id) {
            $jumlah = $bagian[$urut];
            $kartu = [];

            for ($i = 1; $i <= $jumlah; $i++) {
                $kartu[] = $this->barisKartu($board->id, $id, $i, $pemilik->id, $urut);
            }

            foreach (array_chunk($kartu, 500) as $potongan) {
                DB::table('kanban_kartu')->insert($potongan);
            }
            $dibuat += $jumlah;
        }

        $this->lengkapiKartu($board, $labelId, $anggota, $pemilik, $bidangId);

        return $dibuat;
    }

    /** Bagi jatah kartu: satu list dibuat sangat padat, sisanya menurun. */
    private function bagiKartu(int $jumlahList, int $target): array
    {
        $bobot = [];
        for ($i = 0; $i < $jumlahList; $i++) {
            $bobot[] = $i === 0 ? 6 : max(1, 6 - $i);
        }

        $jumlahBobot = array_sum($bobot);

        return array_map(fn ($b) => max(3, (int) round($target * $b / $jumlahBobot)), $bobot);
    }

    private function barisKartu(int $boardId, int $kolomId, int $urut, int $pemilikId, int $urutKolom): array
    {
        $sekolah = self::SEKOLAH[array_rand(self::SEKOLAH)];
        $kota = self::KOTA[array_rand(self::KOTA)];
        $kegiatan = self::KEGIATAN[array_rand(self::KEGIATAN)];
        $kode = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

        $tenggat = match (random_int(1, 5)) {
            1 => now()->subDays(random_int(1, 20)),        // lewat
            2 => now()->addDays(random_int(0, 2)),         // segera
            3 => now()->addDays(random_int(3, 45)),
            default => null,
        };

        $dibuat = now()->subDays(random_int(0, 120))->subMinutes(random_int(0, 1440));

        return [
            'board_id' => $boardId,
            'kolom_id' => $kolomId,
            'posisi' => $urut * Posisi::JARAK,
            'judul' => $kode.'_'.mb_strtoupper($sekolah).' ('.$kota.') — '.$kegiatan,
            'deskripsi' => random_int(0, 2) === 0
                ? '**School PIC:** Mrs. '.Str::random(4)."\nAddress: Jl. Contoh No. ".random_int(1, 99).", {$kota}\n\n- Package ".$kegiatan."\n- Students: ".random_int(20, 240)
                : null,
            'cover_warna' => random_int(0, 6) === 0 ? ['hijau', 'kuning', 'merah', 'biru', 'ungu'][random_int(0, 4)] : null,
            'mulai_pada' => random_int(0, 4) === 0 ? now()->subDays(random_int(1, 30))->toDateString() : null,
            'tenggat_pada' => $tenggat,
            'tenggat_selesai_at' => $tenggat && random_int(0, 3) === 0 ? $tenggat->copy()->subDay() : null,
            'dibuat_oleh' => $pemilikId,
            'diarsipkan_at' => $urutKolom > 0 && random_int(0, 40) === 0 ? now()->subDays(random_int(1, 30)) : null,
            'created_at' => $dibuat,
            'updated_at' => $dibuat,
        ];
    }

    /** Label, anggota, checklist, komentar, dan isi bidang untuk sebagian kartu. */
    private function lengkapiKartu(Board $board, array $labelId, array $anggota, User $pemilik, int $bidangId): void
    {
        $anggota = $anggota ?: [$pemilik->id];

        DB::table('kanban_kartu')->where('board_id', $board->id)->orderBy('id')
            ->chunkById(500, function ($kartu) use ($labelId, $anggota, $bidangId) {
                $pasanganLabel = [];
                $pasanganAnggota = [];
                $isiBidang = [];
                $checklist = [];
                $komentar = [];

                foreach ($kartu as $k) {
                    foreach (collect($labelId)->shuffle()->take(random_int(0, 3)) as $id) {
                        $pasanganLabel[] = ['kartu_id' => $k->id, 'label_id' => $id];
                    }

                    foreach (collect($anggota)->shuffle()->take(random_int(0, 3)) as $id) {
                        $pasanganAnggota[] = ['kartu_id' => $k->id, 'user_id' => $id];
                    }

                    if (random_int(0, 2) === 0) {
                        $isiBidang[] = [
                            'bidang_id' => $bidangId,
                            'kartu_id' => $k->id,
                            'nilai' => 'INV-'.now()->year.'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                            'created_at' => now(), 'updated_at' => now(),
                        ];
                    }

                    if (random_int(0, 3) === 0) {
                        $checklist[] = ['kartu_id' => $k->id, 'judul' => 'Process', 'posisi' => 65536, 'created_at' => now(), 'updated_at' => now()];
                    }

                    if (random_int(0, 4) === 0) {
                        $komentar[] = [
                            'kartu_id' => $k->id,
                            'user_id' => $anggota[array_rand($anggota)],
                            'isi' => ['Already spoke to the PIC.', 'Waiting on schedule confirmation.', 'All files are in.', 'Please double-check this one.'][random_int(0, 3)],
                            'created_at' => now()->subDays(random_int(0, 20)), 'updated_at' => now(),
                        ];
                    }
                }

                $this->sisipkan('kanban_kartu_label', $pasanganLabel);
                $this->sisipkan('kanban_kartu_anggota', $pasanganAnggota);
                $this->sisipkan('kanban_bidang_nilai', $isiBidang);
                $this->sisipkan('kanban_komentar', $komentar);

                if ($checklist) {
                    DB::table('kanban_checklist')->insert($checklist);
                    $this->isiChecklist(collect($checklist)->pluck('kartu_id')->all());
                }
            });
    }

    private function isiChecklist(array $kartuId): void
    {
        $item = [];
        $daftar = ['Confirm the schedule', 'Prepare the gear', 'Class photos', 'Select photos', 'Edit & retouch', 'Send proofing', 'Print'];

        foreach (DB::table('kanban_checklist')->whereIn('kartu_id', $kartuId)->pluck('id') as $id) {
            foreach (collect($daftar)->shuffle()->take(random_int(2, 5))->values() as $urut => $teks) {
                $item[] = [
                    'checklist_id' => $id,
                    'teks' => $teks,
                    'posisi' => ($urut + 1) * 65536,
                    'selesai_at' => random_int(0, 1) ? now()->subDays(random_int(0, 10)) : null,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }

        $this->sisipkan('kanban_checklist_item', $item);
    }

    private function sisipkan(string $tabel, array $baris): void
    {
        foreach (array_chunk($baris, 1000) as $potongan) {
            DB::table($tabel)->insert($potongan);
        }
    }

    private function berwaktu(array $baris): array
    {
        return array_map(fn ($b) => $b + ['created_at' => now(), 'updated_at' => now()], $baris);
    }
}
