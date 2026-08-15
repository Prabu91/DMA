<?php

namespace App\Support;

use App\Models\Cabang;
use App\Models\Order;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Sumber data dashboard per role.
 *
 * Semua query memakai model nyata. Untuk model Order & Sekolah, CabangScope
 * otomatis membatasi ke cabang user (kecuali super_admin & operasional yang
 * melihat semua). Tabel bisa saja masih kosong — view menyediakan empty state.
 */
class DashboardData
{
    public static function for(User $user, string $role): array
    {
        return match ($role) {
            'marketing' => self::marketing($user),
            'tim_event' => self::timEvent($user),
            'super_admin', 'operasional' => self::lintasCabang(),
            'admin_sales' => self::adminSales(),
            'editor' => self::editor(),
            default => [],
        };
    }

    private static function marketing(User $user): array
    {
        // Order milik marketing ini (sudah ter-scope ke cabangnya).
        $base = fn () => Order::where('marketing_id', $user->id);

        return [
            'stats' => [
                ['label' => 'Booking aktif', 'value' => (clone $base())->whereIn('status', OrderStatus::AKTIF)->count(), 'icon_key' => 'order', 'accent' => 'brand'],
                ['label' => 'Menunggu DP', 'value' => (clone $base())->whereIn('status', OrderStatus::MENUNGGU_DP)->count(), 'icon_key' => 'clock', 'accent' => 'pending'],
                ['label' => 'Event minggu ini', 'value' => (clone $base())->whereBetween('tanggal_event', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count(), 'icon_key' => 'calendar', 'accent' => 'info'],
                ['label' => 'Total sekolah', 'value' => Sekolah::count(), 'icon_key' => 'school', 'accent' => 'navy'],
            ],
            'recentOrders' => $base()->with(['sekolah', 'cabang'])->latest()->limit(6)->get(),
        ];
    }

    private static function timEvent(User $user): array
    {
        // Event yang di-assign ke user ini via pivot order_tim_event —
        // hanya yang order-nya sudah di-assign marketing (siap dikerjakan).
        $assigned = $user->ordersAsTimEvent()->whereNotNull('marketing_id')->with('sekolah');

        return [
            'stats' => [
                ['label' => 'Event ditugaskan', 'value' => (clone $assigned)->count(), 'icon_key' => 'calendar', 'accent' => 'brand'],
                ['label' => 'Dijadwalkan', 'value' => (clone $assigned)->where('event_status', OrderStatus::EVENT_DIJADWALKAN)->count(), 'icon_key' => 'clock', 'accent' => 'info'],
                ['label' => 'Selesai', 'value' => (clone $assigned)->where('event_status', OrderStatus::EVENT_SELESAI)->count(), 'icon_key' => 'check', 'accent' => 'success'],
            ],
            'assignedEvents' => $assigned->orderBy('tanggal_event')->limit(10)->get(),
        ];
    }

    private static function lintasCabang(): array
    {
        return [
            'stats' => [
                ['label' => 'Total cabang', 'value' => Cabang::count(), 'icon_key' => 'building', 'accent' => 'navy'],
                ['label' => 'Total order', 'value' => Order::count(), 'icon_key' => 'order', 'accent' => 'brand'],
                ['label' => 'Total sekolah', 'value' => Sekolah::count(), 'icon_key' => 'school', 'accent' => 'info'],
                ['label' => 'Total produk', 'value' => Produk::count(), 'icon_key' => 'product', 'accent' => 'success'],
            ],
            // Rekap order per cabang (withCount tidak terpengaruh CabangScope Order
            // karena super_admin/operasional dikecualikan dari scope).
            'perCabang' => Cabang::withCount('orders')->orderBy('nama')->get(),
            'recentOrders' => Order::with(['sekolah', 'cabang'])->latest()->limit(6)->get(),
        ];
    }

    private static function adminSales(): array
    {
        // area ter-scope ke cabangnya sendiri oleh CabangScope.
        return [
            'stats' => [
                ['label' => 'Order cabang', 'value' => Order::count(), 'icon_key' => 'order', 'accent' => 'brand'],
                ['label' => 'Booking aktif', 'value' => Order::whereIn('status', OrderStatus::AKTIF)->count(), 'icon_key' => 'clock', 'accent' => 'pending'],
                ['label' => 'Sekolah cabang', 'value' => Sekolah::count(), 'icon_key' => 'school', 'accent' => 'info'],
                ['label' => 'Event minggu ini', 'value' => Order::whereBetween('tanggal_event', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count(), 'icon_key' => 'calendar', 'accent' => 'navy'],
            ],
            'recentOrders' => Order::with(['sekolah', 'cabang'])->latest()->limit(6)->get(),
        ];
    }

    private static function editor(): array
    {
        return [
            'stats' => [
                ['label' => 'Antrian desain', 'value' => 0, 'icon_key' => 'photo', 'accent' => 'brand', 'placeholder' => true],
                ['label' => 'Selesai hari ini', 'value' => 0, 'icon_key' => 'check', 'accent' => 'success', 'placeholder' => true],
                ['label' => 'Total produk', 'value' => Produk::count(), 'icon_key' => 'product', 'accent' => 'info'],
            ],
        ];
    }
}
