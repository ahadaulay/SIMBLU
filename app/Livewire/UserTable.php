<?php

namespace App\Livewire;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class UserTable extends Component
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
        'name' => '',
        'username' => '',
        'email' => '',
        'password' => '',
        'role' => 'UNIT',
        'unit_id' => null,
        'active' => true,
    ];

    public string $reason = '';

    #[Locked]
    public ?int $unitId = null;

    public bool $createOnLoad = false;

    public function mount(): void
    {
        $unitId = request()->integer('unit');
        $this->unitId = Unit::whereKey($unitId)->exists() ? $unitId : null;
        $this->createOnLoad = $this->unitId !== null && request()->boolean('create');
    }

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
        $this->validateOnly('perPage', [
            'perPage' => ['integer', Rule::in([5, 10, 25])],
        ]);

        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        abort_unless(in_array($field, ['name', 'created_at']), 400);
        $this->sortDirection = $this->sortField === $field
            && $this->sortDirection === 'asc'
                ? 'desc'
                : 'asc';

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
        $this->dispatch('open-user-modal');
    }

    public function openContextCreateModal(): void
    {
        $this->openCreateModal();
        $this->form['role'] = 'UNIT';
        $this->form['unit_id'] = $this->unitId;
        $this->createOnLoad = false;
    }

    public function openEditModal(int $id): void
    {
        $record = User::findOrFail($id);
        $this->editingId = $record->id;
        $this->form = $record->only([
            'name',
            'username',
            'email',
            'password',
            'role',
            'unit_id',
            'active',
        ]);

        $this->form['password'] = '';

        $this->reason = '';
        $this->resetValidation();
        $this->dispatch('open-user-modal');
    }

    public function save(): void
    {
        Gate::authorize('reporting-admin');
        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:150'],
            'form.username' => [
                'required',
                'alpha_dash:ascii',
                'max:100',
                Rule::unique('users', 'username')->ignore($this->editingId),
            ],
            'form.email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'form.password' => [
                $this->editingId ? 'nullable' : 'required',
                Password::min(10)->letters()->numbers(),
            ],
            'form.role' => [
                'required',
                Rule::in(['ADMIN', 'UNIT', 'PIMPINAN']),
            ],
            'form.unit_id' => [
                Rule::requiredIf(($this->form['role'] ?? '') === 'UNIT'),
                'nullable',
                Rule::exists('units', 'id')->where('active', true),
            ],
            'form.active' => ['boolean'],
        ]);

        // Penguncian baris mencegah dua admin menimpa perubahan pengguna secara bersamaan.
        DB::transaction(function () use ($validated): void {
            $data = $validated['form'];
            $record = $this->editingId
                ? User::lockForUpdate()->findOrFail($this->editingId)
                : new User;

            if (empty($data['password'])) {
                unset($data['password']);
            }

            if ($data['role'] !== 'UNIT') {
                $data['unit_id'] = null;
            }

            // Admin yang sedang masuk tidak boleh mencabut akses administrasinya sendiri.
            if ($record->id === auth()->id() && (! $data['active'] || $data['role'] !== 'ADMIN')) {
                throw ValidationException::withMessages([
                    'form.role' => 'Akun admin sendiri harus tetap aktif sebagai admin.',
                ]);
            }

            $record->fill($data)->save();
        });

        session()->flash('success', 'Pengguna berhasil disimpan.');
        $this->dispatch('close-user-modal');
        $this->resetForm();
    }

    public function render(): View
    {
        $search = trim($this->search);
        $sortField = in_array($this->sortField, ['name', 'created_at'], true)
            ? $this->sortField
            : 'name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $perPage = in_array($this->perPage, [5, 10, 25], true)
            ? $this->perPage
            : 10;

        $records = User::query()
            ->when($this->unitId, fn ($query) => $query->where('unit_id', $this->unitId))
            ->when(
                $search !== '',
                fn ($query) => $query->where('name', 'like', '%'.$search.'%')
            )
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage);

        return view('livewire.user-table', [
            'records' => $records,
            'units' => Unit::orderBy('name')->get(['id', 'name']),
            'selectedUnit' => $this->unitId ? Unit::find($this->unitId) : null,
        ]);
    }
}
