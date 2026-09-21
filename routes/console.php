<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bersihkan order di sampah yang lewat masa retensi (butuh cron scheduler aktif).
Schedule::command('orders:purge-trash')->dailyAt('02:30');

// Pengingat tenggat kartu kanban (butuh cron scheduler aktif).
Schedule::command('kanban:ingatkan-tenggat')->hourly();
