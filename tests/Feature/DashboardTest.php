<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'marketing'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_dashboard_mengarahkan_user_ke_dashboard_sesuai_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('marketing');

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertRedirect(route('app.dashboard.marketing'));
    }

    public function test_dashboard_role_menampilkan_placeholder_yang_benar(): void
    {
        $user = User::factory()->create();
        $user->assignRole('marketing');

        $this->actingAs($user)
            ->get(route('app.dashboard.marketing'))
            ->assertOk()
            ->assertSee('Dashboard marketing');
    }

    public function test_user_tidak_bisa_membuka_dashboard_role_lain(): void
    {
        $user = User::factory()->create();
        $user->assignRole('marketing');

        $this->actingAs($user)
            ->get(route('app.dashboard.super_admin'))
            ->assertForbidden();
    }
}
