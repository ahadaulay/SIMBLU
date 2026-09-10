<?php

namespace Tests\Feature;

use App\Models\Gedung;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_displays_gedung_summary_and_recent_data(): void
    {
        $user = User::factory()->create();
        Gedung::create([
            'nama' => 'Gedung Rektorat',
            'kode' => 'GR-01',
            'keterangan' => 'Gedung administrasi utama.',
        ]);
        Gedung::create(['nama' => 'Gedung Tanpa Kode']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Selamat datang, '.$user->name)
            ->assertSee('Total Gedung')
            ->assertSee('Gedung Rektorat')
            ->assertSee('Belum Ada Kode')
            ->assertSee('Detail');
    }
}
