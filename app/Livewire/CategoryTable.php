<?php

namespace App\Livewire;

use App\Models\IndicatorCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class CategoryTable extends Component
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
        $this->dispatch('open-category-modal');
    }

    public function openEditModal(int $id): void
    {
        $record = IndicatorCategory::findOrFail($id);
        $this->editingId = $record->id;
        $this->form = $record->only(array_keys(['code' => '', 'name' => '', 'active' => true]));

        $this->reason = '';
        $this->resetValidation();
        $this->dispatch('open-category-modal');
    }

    public function save(): void
    {
        Gate::authorize('reporting-admin');
        $validated = $this->validate([
            'form.code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('indicator_categories', 'code')->ignore($this->editingId),
            ],
            'form.name' => ['required', 'string', 'max:150'],
            'form.active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            $data = $validated['form'];
            $record = $this->editingId
                ? IndicatorCategory::lockForUpdate()->findOrFail($this->editingId)
                : new IndicatorCategory;

            $record->fill($data)->save();
        });
        session()->flash('success', 'Kategori Indikator berhasil disimpan.');
        $this->dispatch('close-category-modal');
        $this->resetForm();
    }

    public function render(): View
    {
        $search = trim($this->search);

        $records = IndicatorCategory::query()
            ->when(
                $search !== '',
                fn ($query) => $query->where('name', 'like', '%'.$search.'%')
            )
            ->orderBy(
                in_array($this->sortField, ['name', 'created_at'], true) ? $this->sortField : 'name',
                $this->sortDirection === 'desc' ? 'desc' : 'asc'
            )
            ->paginate(in_array($this->perPage, [5, 10, 25]) ? $this->perPage : 10);

        return view('livewire.category-table', ['records' => $records]);
    }
}
