<?php

namespace App\Livewire;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class UnitTable extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $search = '';

    public int $perPage = 10;

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    #[Locked]
    public ?int $editingId = null;

    public array $form = [
        'code' => '',
        'name' => '',
        'parent_id' => null,
        'active' => true,
    ];

    public string $reason = '';

    public function boot(): void
    {
        Gate::authorize('reporting-admin');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->validateOnly('perPage', ['perPage' => ['integer', Rule::in([5, 10, 25])]]);
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        abort_unless(in_array($field, ['name', 'created_at']), 400);
        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'form', 'reason']);
        $this->resetValidation();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->dispatch('open-unit-modal');
    }

    public function openEditModal(int $id): void
    {
        $record = Unit::findOrFail($id);
        $this->editingId = $record->id;
        $this->form = $record->only(array_keys(['code' => '', 'name' => '', 'parent_id' => null, 'active' => true]));

        $this->reason = '';
        $this->resetValidation();
        $this->dispatch('open-unit-modal');
    }

    public function save(): void
    {
        Gate::authorize('reporting-admin');
        $validated = $this->validate([
            'form.code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('units', 'code')->ignore($this->editingId),
            ],
            'form.name' => ['required', 'string', 'max:150'],
            'form.parent_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
            'form.active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            $data = $validated['form'];
            $parents = Unit::orderBy('id')->lockForUpdate()->pluck('parent_id', 'id');
            $record = $this->editingId
                ? Unit::lockForUpdate()->findOrFail($this->editingId)
                : new Unit;
            $parent = $data['parent_id'];
            $visited = [];

            // Telusuri seluruh induk agar unit tidak menjadi induk bagi dirinya sendiri.
            while ($parent) {
                if ($parent == $this->editingId || in_array($parent, $visited)) {
                    throw ValidationException::withMessages([
                        'form.parent_id' => 'Hierarki unit tidak boleh berputar.',
                    ]);
                }

                $visited[] = $parent;
                $parent = $parents->get($parent);
            }

            $record->fill($data)->save();
        });
        session()->flash('success', 'Unit Kerja berhasil disimpan.');
        $this->dispatch('close-unit-modal');
        $this->resetForm();
    }

    public function render(): View
    {
        $records = Unit::query()->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->orderBy(in_array($this->sortField, ['name', 'created_at']) ? $this->sortField : 'name', $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate(in_array($this->perPage, [5, 10, 25]) ? $this->perPage : 10);

        return view('livewire.unit-table', [
            'records' => $records,
            'units' => Unit::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
