<?php

namespace App\Livewire;

use App\Models\ReportingPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class PeriodTable extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $search = '';

    public int $perPage = 10;

    public string $sortField = 'year';

    public string $sortDirection = 'asc';

    #[Locked]
    public ?int $editingId = null;

    public array $form = [
        'year' => 2026,
        'quarter' => 1,
        'deadline' => '',
        'status' => 'DRAFT',
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
        abort_unless(in_array($field, ['year', 'created_at']), 400);
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
        $this->dispatch('open-period-modal');
    }

    public function openEditModal(int $id): void
    {
        $record = ReportingPeriod::findOrFail($id);
        $this->editingId = $record->id;
        $this->form = $record->only([
            'year',
            'quarter',
            'deadline',
            'status',
        ]);

        $this->form['deadline'] = $record->deadline->format('Y-m-d');
        $this->reason = '';
        $this->resetValidation();
        $this->dispatch('open-period-modal');
    }

    public function save(): void
    {
        Gate::authorize('reporting-admin');
        $validated = $this->validate([
            'form.year' => ['required', 'integer', 'between:2000,2100'],
            'form.quarter' => [
                'required',
                'integer',
                'between:1,4',
                Rule::unique('reporting_periods', 'quarter')
                    ->where('year', $this->form['year'])
                    ->ignore($this->editingId),
            ],
            'form.deadline' => ['required', 'date_format:Y-m-d'],
            'form.status' => [
                'required',
                Rule::in(['DRAFT', 'OPEN', 'CLOSED', 'LOCKED']),
            ],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated): void {
            $data = $validated['form'];
            $record = $this->editingId
                ? ReportingPeriod::lockForUpdate()->findOrFail($this->editingId)
                : new ReportingPeriod;

            if ($record->exists) {
                $periodChanged = $record->year != $data['year']
                    || $record->quarter != $data['quarter'];

                if ($record->reports()->exists() && $periodChanged) {
                    throw ValidationException::withMessages([
                        'form.year' => 'Periode yang sudah digunakan tidak dapat diganti tahun/triwulannya.',
                    ]);
                }

                if ($record->status !== $data['status']) {
                    $this->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);

                    if ($data['status'] === 'DRAFT' && $record->reports()->exists()) {
                        throw ValidationException::withMessages([
                            'form.status' => 'Periode yang sudah digunakan tidak dapat menjadi draf.',
                        ]);
                    }

                    // Setiap perubahan status disimpan agar riwayat periode dapat diaudit.
                    DB::table('period_histories')->insert([
                        'period_id' => $record->id,
                        'user_id' => auth()->id(),
                        'from_status' => $record->status,
                        'to_status' => $data['status'],
                        'note' => $this->reason,
                        'created_at' => now(),
                    ]);
                }
            }

            $record->fill($data)->save();
        });
        session()->flash('success', 'Periode Pelaporan berhasil disimpan.');
        $this->dispatch('close-period-modal');
        $this->resetForm();
    }

    public function render(): View
    {
        $records = ReportingPeriod::query()->when(trim($this->search) !== '', fn ($q) => $q->where('year', 'like', '%'.trim($this->search).'%'))
            ->orderBy(in_array($this->sortField, ['year', 'created_at']) ? $this->sortField : 'year', $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate(in_array($this->perPage, [5, 10, 25]) ? $this->perPage : 10);

        return view('livewire.period-table', ['records' => $records]);
    }
}
