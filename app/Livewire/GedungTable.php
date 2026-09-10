<?php

namespace App\Livewire;

use App\Models\Gedung;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GedungTable extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $search = '';

    public int $perPage = 10;

    public string $sortField = 'nama';

    public string $sortDirection = 'asc';

    public ?int $editingBuildingId = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->validateOnly('perPage', [
            'perPage' => ['integer', Rule::in([5, 10, 25])],
        ]);

        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        abort_unless(in_array($field, ['nama', 'kode', 'created_at'], true), 400);

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->dispatch('open-gedung-modal');
    }

    public function openEditModal(int $id): void
    {
        $building = Gedung::findOrFail($id);

        $this->editingBuildingId = $building->id;
        $this->name = $building->nama;
        $this->code = (string) $building->kode;
        $this->description = (string) $building->keterangan;
        $this->resetValidation();
        $this->dispatch('open-gedung-modal');
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('gedungs', 'kode')->ignore($this->editingBuildingId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => 'nama gedung',
            'code' => 'kode gedung',
            'description' => 'keterangan',
        ]);

        $isEditing = $this->editingBuildingId !== null;
        $building = $isEditing
            ? Gedung::findOrFail($this->editingBuildingId)
            : new Gedung;

        $building->fill([
            'nama' => $data['name'],
            'kode' => $data['code'] ?: null,
            'keterangan' => $data['description'] ?: null,
        ])->save();

        session()->flash('success', $isEditing
            ? 'Gedung berhasil diperbarui.'
            : 'Gedung berhasil ditambahkan.');

        $this->dispatch('close-gedung-modal');
        $this->resetForm();
    }

    public function deleteBuilding(int $id): void
    {
        Gedung::findOrFail($id)->delete();
        session()->flash('success', 'Gedung berhasil dihapus.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingBuildingId', 'name', 'code', 'description']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $search = trim($this->search);
        $buildings = Gedung::query()
            ->select(['id', 'nama', 'kode', 'keterangan', 'created_at'])
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search): void {
                $nested->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('kode', 'like', '%'.$search.'%')
                    ->orWhere('keterangan', 'like', '%'.$search.'%');
            }))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.gedung-table', compact('buildings'));
    }
}
