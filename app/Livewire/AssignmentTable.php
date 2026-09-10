<?php

namespace App\Livewire;

use App\Models\Indicator;
use App\Models\IndicatorAssignment;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\UnitReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class AssignmentTable extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Locked]
    public ?int $editingId = null;

    public string $search = '';

    public array $form = [
        'period_id' => '',
        'unit_id' => '',
        'indicator_id' => '',
        'responsible_user_id' => '',
        'required' => true,
    ];

    #[Locked]
    public ?int $periodId = null;

    public bool $createOnLoad = false;

    #[Locked]
    public ?int $detailPeriodId = null;

    #[Locked]
    public ?int $detailUnitId = null;

    public bool $detailMode = false;

    public function mount(): void
    {
        $periodId = request()->integer('period');
        $this->periodId = ReportingPeriod::whereKey($periodId)->exists() ? $periodId : null;
        $this->createOnLoad = $this->periodId !== null && request()->boolean('create');
    }

    public function boot(): void
    {
        Gate::authorize('reporting-admin');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->reset(['form', 'editingId', 'detailPeriodId', 'detailUnitId', 'detailMode']);
        $this->form['period_id'] = $this->periodId;
        $this->resetValidation();
        $this->dispatch('open-assignment-modal');
    }

    public function updatedFormUnitId(): void
    {
        $this->form['responsible_user_id'] = '';
    }

    public function openContextCreateModal(): void
    {
        $this->openCreateModal();
        $this->form['period_id'] = $this->periodId;
        $this->createOnLoad = false;
    }

    public function openDetail(int $periodId, int $unitId): void
    {
        IndicatorAssignment::query()
            ->where('period_id', $periodId)
            ->where('unit_id', $unitId)
            ->firstOrFail();

        $this->reset(['form', 'editingId']);
        $this->resetValidation();
        $this->detailPeriodId = $periodId;
        $this->detailUnitId = $unitId;
        $this->detailMode = true;
        $this->form['period_id'] = $periodId;
        $this->form['unit_id'] = $unitId;
        $this->dispatch('open-assignment-modal');
    }

    public function editIndicator(int $id): void
    {
        $record = IndicatorAssignment::query()
            ->where('period_id', $this->detailPeriodId)
            ->where('unit_id', $this->detailUnitId)
            ->findOrFail($id);

        $this->editingId = $record->id;
        $this->form = $record->only(array_keys($this->form));
        $this->resetValidation();
    }

    public function cancelIndicatorEdit(): void
    {
        $this->editingId = null;
        $this->form = [
            'period_id' => $this->detailPeriodId,
            'unit_id' => $this->detailUnitId,
            'indicator_id' => '',
            'responsible_user_id' => '',
            'required' => true,
        ];
        $this->resetValidation();
    }

    public function deleteIndicator(int $id): void
    {
        Gate::authorize('reporting-admin');

        $closeModal = DB::transaction(function () use ($id): bool {
            $period = ReportingPeriod::lockForUpdate()->findOrFail($this->detailPeriodId);
            $assignment = IndicatorAssignment::query()
                ->where('period_id', $period->id)
                ->where('unit_id', $this->detailUnitId)
                ->lockForUpdate()
                ->findOrFail($id);
            $reportExists = UnitReport::query()
                ->where('period_id', $period->id)
                ->where('unit_id', $this->detailUnitId)
                ->exists();

            if (! in_array($period->status, ['DRAFT', 'OPEN'], true) || $reportExists) {
                throw ValidationException::withMessages([
                    'form.indicator_id' => 'Indikator tidak dapat dihapus setelah laporan unit dibuat atau periode ditutup.',
                ]);
            }

            $assignment->delete();

            return ! IndicatorAssignment::query()
                ->where('period_id', $period->id)
                ->where('unit_id', $this->detailUnitId)
                ->exists();
        });

        session()->flash('success', 'Indikator berhasil dihapus dari penugasan unit.');
        $this->cancelIndicatorEdit();

        if ($closeModal) {
            $this->reset(['detailPeriodId', 'detailUnitId', 'detailMode']);
            $this->dispatch('close-assignment-modal');
        }
    }

    public function openEditModal(int $id): void
    {
        $record = IndicatorAssignment::findOrFail($id);
        $this->editingId = $id;
        $this->form = $record->only(array_keys($this->form));
        $this->resetValidation();
        $this->dispatch('open-assignment-modal');
    }

    public function save(): void
    {
        Gate::authorize('reporting-admin');
        $data = $this->validate([
            'form.period_id' => ['required', Rule::exists('reporting_periods', 'id')],
            'form.unit_id' => ['required', Rule::exists('units', 'id')->where('active', true)],
            'form.indicator_id' => [
                'required',
                Rule::exists('indicators', 'id')->where('active', true),
                Rule::unique('indicator_assignments', 'indicator_id')
                    ->where('period_id', $this->form['period_id'])
                    ->where('unit_id', $this->form['unit_id'])
                    ->ignore($this->editingId),
            ],
            'form.responsible_user_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('unit_id', $this->form['unit_id'])
                    ->where('role', 'UNIT')
                    ->where('active', true),
            ],
            'form.required' => ['boolean'],
        ])['form'];
        DB::transaction(function () use ($data): void {
            $record = $this->editingId
                ? IndicatorAssignment::findOrFail($this->editingId)
                : new IndicatorAssignment;

            // Kunci pasangan periode-unit secara berurutan untuk mencegah konflik transaksi.
            $pairs = [[$data['period_id'], $data['unit_id']]];

            if ($record->exists) {
                $pairs[] = [$record->period_id, $record->unit_id];
            }

            sort($pairs);

            foreach ($pairs as [$periodId, $unitId]) {
                $period = ReportingPeriod::lockForUpdate()->findOrFail($periodId);
                $reportExists = UnitReport::where('period_id', $periodId)
                    ->where('unit_id', $unitId)
                    ->exists();

                if (! in_array($period->status, ['DRAFT', 'OPEN'], true) || $reportExists) {
                    throw ValidationException::withMessages([
                        'form.period_id' => 'Penugasan hanya dapat diubah sebelum laporan unit dibuat dan sebelum periode ditutup.',
                    ]);
                }
            }

            $record->fill($data + ['assigned_by' => auth()->id()])->save();
        });

        session()->flash('success', 'Penugasan disimpan.');

        if ($this->detailMode) {
            $this->cancelIndicatorEdit();

            return;
        }

        $this->dispatch('close-assignment-modal');
        $this->reset(['form', 'editingId']);
    }

    public function render(): View
    {
        return view('livewire.assignment-group-table', [
            'records' => IndicatorAssignment::query()
                ->select(['period_id', 'unit_id'])
                ->selectRaw('COUNT(*) as indicators_count')
                ->with(['period', 'unit'])
                ->when($this->periodId, fn ($query) => $query->where('period_id', $this->periodId))
                ->whereHas(
                    'unit',
                    fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%')
                )
                ->groupBy(['period_id', 'unit_id'])
                ->orderByRaw('MAX(created_at) DESC')
                ->paginate(10),
            'periods' => ReportingPeriod::orderByDesc('year')
                ->orderByDesc('quarter')
                ->get(),
            'units' => Unit::where('active', true)
                ->orderBy('name')
                ->get(),
            'indicators' => Indicator::where('active', true)
                ->orderBy('name')
                ->get(),
            'selectedPeriod' => $this->periodId ? ReportingPeriod::find($this->periodId) : null,
            'detailPeriod' => $this->detailPeriodId ? ReportingPeriod::find($this->detailPeriodId) : null,
            'detailUnit' => $this->detailUnitId ? Unit::find($this->detailUnitId) : null,
            'detailAssignments' => $this->detailMode
                ? IndicatorAssignment::with(['indicator', 'responsible'])
                    ->where('period_id', $this->detailPeriodId)
                    ->where('unit_id', $this->detailUnitId)
                    ->orderBy('indicator_id')
                    ->get()
                : collect(),
            'responsibleUsers' => $this->form['unit_id']
                ? User::where('unit_id', $this->form['unit_id'])
                    ->where('role', 'UNIT')
                    ->where('active', true)
                    ->orderBy('name')
                    ->get()
                : collect(),
        ]);
    }
}
