<?php

namespace App\Console\Commands;

use App\Models\Kanban\Kartu;
use App\Services\Kanban\Kabar;
use Illuminate\Console\Command;

/**
 * Ingatkan tenggat kartu yang jatuh dalam 24 jam ke depan (mirip pengingat
 * Trello). Dijalankan terjadwal; tiap kartu hanya diingatkan sekali.
 */
class KanbanIngatkanTenggat extends Command
{
    protected $signature = 'kanban:ingatkan-tenggat {--jam=24 : Ingatkan kartu yang tenggatnya dalam sekian jam}';

    protected $description = 'Kirim pengingat tenggat kartu kanban ke pengikut & anggota kartu';

    public function handle(Kabar $kabar): int
    {
        $batas = now()->addHours((int) $this->option('jam'));

        $kartu = Kartu::query()
            ->whereNull('diarsipkan_at')
            ->whereNull('tenggat_selesai_at')
            ->whereNull('diingatkan_at')
            ->whereNotNull('tenggat_pada')
            ->where('tenggat_pada', '<=', $batas)
            ->where('tenggat_pada', '>=', now()->subDay())
            ->with('board')
            ->get();

        $orang = 0;
        foreach ($kartu as $k) {
            $orang += $kabar->tenggat($k);
            $k->forceFill(['diingatkan_at' => now()])->saveQuietly();
        }

        $this->info("Pengingat tenggat: {$kartu->count()} kartu, {$orang} penerima.");

        return self::SUCCESS;
    }
}
