<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderStatus;
use App\Support\TahapOrder;
use Illuminate\Support\Carbon;

/**
 * Aturan memindah kartu di papan order. Semua jalur (seret, tombol "Pindah
 * ke", halaman order) lewat sini, supaya aturan yang di Trello hanya
 * mengandalkan disiplin orang dijaga di satu tempat.
 *
 * Pelanggaran aturan dilempar sebagai PapanDitolak berisi pesan yang siap
 * ditampilkan ke pengguna.
 */
class PapanOrder
{
    // ---------------- Hak ----------------

    public function pengelola(User $user): bool
    {
        return $user->hasAnyRole(TahapOrder::PERAN_PENGELOLA);
    }

    /** Boleh menyentuh kartu ini sama sekali? */
    public function bolehKelola(User $user, Order $order): bool
    {
        if (! TahapOrder::ada($order->tahap) || $order->status === OrderStatus::BATAL) {
            return false;
        }
        if ($this->pengelola($user)) {
            return true;
        }
        if ($user->hasRole('editor')) {
            return $order->tahap === 'E';
        }
        if ($user->hasRole('marketing')) {
            return $order->tahap === 'F' && (int) $order->marketing_id === (int) $user->id;
        }

        return false;
    }

    /** Tahap tujuan yang boleh dipilih pengguna untuk kartu ini. */
    public function tahapTujuan(User $user, Order $order): array
    {
        if (! $this->bolehKelola($user, $order)) {
            return [];
        }
        if ($this->pengelola($user)) {
            return array_keys(TahapOrder::DAFTAR);
        }
        if ($user->hasRole('editor')) {
            return ['E', 'F'];                // selesai edit → QC marketing
        }

        return ['E', 'F', 'G'];               // marketing: revisi ke editor, atau lolos ke produksi
    }

    // ---------------- Aksi ----------------

    public function pindahTahap(Order $order, string $tujuan, User $oleh): void
    {
        if (! TahapOrder::ada($order->tahap)) {
            throw new PapanDitolak('Order ini belum masuk papan — konfirmasi Hari-H dulu di halaman event.');
        }
        if (! TahapOrder::ada($tujuan)) {
            throw new PapanDitolak('Tahap tujuan tidak dikenal.');
        }
        if (! in_array($tujuan, $this->tahapTujuan($oleh, $order), true)) {
            throw new PapanDitolak('Anda tidak berwenang memindah kartu ini ke '.TahapOrder::label($tujuan).'.');
        }
        if ($tujuan === $order->tahap) {
            return;
        }
        if ($tujuan === TahapOrder::SELESAI && $order->status !== OrderStatus::LUNAS) {
            throw new PapanDitolak('Order belum lunas — belum bisa ditandai selesai.');
        }

        $dari = $order->tahap;
        $order->update([
            'tahap' => $tujuan,
            'tahap_masuk_at' => now(),
            // Di QC marketing, kartu otomatis dipegang marketing order itu.
            'tahap_pj_id' => $tujuan === 'F' ? $order->marketing_id : null,
            'tenggat_manual' => null,
            'tertahan_alasan' => null,
            'tertahan_at' => null,
        ]);
        $order->catat('papan_pindah', TahapOrder::label($dari).' → '.TahapOrder::label($tujuan), ['dari' => $dari, 'ke' => $tujuan], $oleh->id);
    }

    /** Tugaskan (atau lepas, bila $pjId null) penanggung jawab kartu di tahapnya. */
    public function tugaskan(Order $order, ?int $pjId, User $oleh): void
    {
        if (! $this->bolehKelola($oleh, $order)) {
            throw new PapanDitolak('Anda tidak berwenang menugaskan kartu ini.');
        }
        // Editor hanya boleh mengambil kartu untuk dirinya sendiri, atau melepasnya.
        if (! $this->pengelola($oleh) && $pjId !== null && $pjId !== (int) $oleh->id) {
            throw new PapanDitolak('Anda hanya bisa mengambil kartu untuk diri sendiri.');
        }
        if ($pjId !== null && ! User::whereKey($pjId)->exists()) {
            throw new PapanDitolak('Pengguna tidak ditemukan.');
        }
        if ((int) $order->tahap_pj_id === (int) $pjId) {
            return;
        }

        $order->update(['tahap_pj_id' => $pjId]);
        $nama = $pjId ? (User::find($pjId)?->nama ?? User::find($pjId)?->name) : null;
        $order->catat('papan_tugas', TahapOrder::label($order->tahap).': '.($nama ?? 'belum ditugaskan'), ['pj' => $pjId], $oleh->id);
    }

    public function tahan(Order $order, string $alasan, User $oleh): void
    {
        $alasan = trim($alasan);
        if (! $this->bolehKelola($oleh, $order)) {
            throw new PapanDitolak('Anda tidak berwenang menandai kartu ini.');
        }
        if ($alasan === '') {
            throw new PapanDitolak('Tulis alasan kartu ini tertahan.');
        }

        $order->update(['tertahan_alasan' => mb_substr($alasan, 0, 255), 'tertahan_at' => now()]);
        $order->catat('papan_tertahan', $alasan, [], $oleh->id);
    }

    public function lanjutkan(Order $order, User $oleh): void
    {
        if (! $this->bolehKelola($oleh, $order)) {
            throw new PapanDitolak('Anda tidak berwenang mengubah kartu ini.');
        }
        if ($order->tertahan_alasan === null) {
            return;
        }

        $order->update(['tertahan_alasan' => null, 'tertahan_at' => null]);
        $order->catat('papan_lanjut', null, [], $oleh->id);
    }

    /** Tenggat yang ditetapkan SPV; null = kembali ke tenggat hitungan. */
    public function aturTenggat(Order $order, ?string $tanggal, User $oleh): void
    {
        if (! $this->pengelola($oleh) || ! $this->bolehKelola($oleh, $order)) {
            throw new PapanDitolak('Hanya admin yang bisa menetapkan tenggat.');
        }

        $order->update(['tenggat_manual' => $tanggal ? Carbon::parse($tanggal)->toDateString() : null]);
        $order->catat('papan_tenggat', $tanggal ? Carbon::parse($tanggal)->translatedFormat('d M Y') : 'kembali otomatis', [], $oleh->id);
    }
}
