<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/app/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/app/profile', [
                'nama' => 'Test User',
                'email' => 'test@example.com',
                'no_telp' => '0812345678',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/app/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->nama);
        $this->assertSame('Test User', $user->name); // name disinkronkan dari nama
        $this->assertSame('0812345678', $user->no_telp);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/app/profile', [
                'nama' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/app/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }
}
