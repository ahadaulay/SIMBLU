<?php

namespace Tests\Feature;

use App\Livewire\GedungTable;
use App\Models\Gedung;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GedungCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_guest_cannot_open_gedung_pages(): void
    {
        auth()->logout();
        $this->get(route('gedungs.index'))->assertRedirect(route('login'));
    }

    public function test_gedung_index_contains_livewire_component(): void
    {
        Gedung::create([
            'nama' => 'Gedung Perpustakaan',
            'kode' => 'GP-01',
        ]);

        $this->get(route('gedungs.index'))
            ->assertOk()
            ->assertSeeLivewire(GedungTable::class)
            ->assertSee('Gedung Perpustakaan')
            ->assertSee('Detail');
    }

    public function test_user_can_create_read_update_and_delete_gedung_with_livewire(): void
    {
        Livewire::test(GedungTable::class)
            ->set('name', 'Gedung Rektorat')
            ->set('code', 'GR-01')
            ->set('description', 'Gedung administrasi.')
            ->call('save')
            ->assertHasNoErrors();

        $building = Gedung::where('kode', 'GR-01')->firstOrFail();

        $this->get(route('gedungs.show', $building))
            ->assertOk()
            ->assertSee('Gedung Rektorat');

        Livewire::test(GedungTable::class)
            ->call('openEditModal', $building->id)
            ->set('name', 'Gedung Rektorat Baru')
            ->set('code', 'GR-02')
            ->set('description', 'Sudah diperbarui.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('gedungs', [
            'id' => $building->id,
            'nama' => 'Gedung Rektorat Baru',
            'kode' => 'GR-02',
        ]);

        Livewire::test(GedungTable::class)
            ->call('deleteBuilding', $building->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('gedungs', ['id' => $building->id]);
    }

    public function test_user_can_search_buildings_and_only_use_allowed_page_sizes(): void
    {
        Gedung::create(['nama' => 'Gedung Rektorat', 'kode' => 'GR-01']);
        Gedung::create(['nama' => 'Gedung Perpustakaan', 'kode' => 'GP-01']);

        Livewire::test(GedungTable::class)
            ->set('search', 'Rektorat')
            ->assertSee('Gedung Rektorat')
            ->assertDontSee('Gedung Perpustakaan')
            ->set('perPage', 100)
            ->assertHasErrors('perPage');
    }
}
