<?php

namespace App\Livewire;

use App\Models\ReportingCycle;
use App\Models\ReportingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ReportingCycleTable extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $search = '';

    public int $perPage = 10;

    public array $form = [
        'year' => 2026,
        'frequency' => 'QUARTERLY',
    ];

    #[Locked]
    public ?int $detailCycleId = null;

    public array $segmentStatuses = [];

    public function mount(): void
    {
        $this->form['year'] = (int) now()->year;
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

    public function openCreateModal(): void
    {
        $this->form = [
            'year' => (int) now()->year,
            'frequency' => 'QUARTERLY',
        ];
        $this->resetValidation();
        $this->dispatch('open-cycle-modal');
    }

    public function save(): void
    {
        Gate::authorize('reporting-admin');

        $data = $this->validate([
            'form.year' => [
                'required',
                'integer',
                'between:2000,2100',
                Rule::unique('reporting_cycles', 'year'),
            ],
            'form.frequency' => [
                'required',
                Rule::in(['MONTHLY', 'QUARTERLY', 'SEMESTERLY']),
            ],
        ])['form'];

        DB::transaction(function () use ($data): void {
            $cycle = ReportingCycle::create([
                'year' => $data['year'],
                'frequency' => $data['frequency'],
                'created_by' => auth()->id(),
            ]);

            foreach ($this->segments($data['year'], $data['frequency']) as $segment) {
                $cycle->periods()->create($segment + [
                    'year' => $data['year'],
                    'status' => 'DRAFT',
                ]);
            }
        });

        session()->flash('success', 'Periode tahun dan seluruh jadwal pengisiannya berhasil dibuat.');
        $this->dispatch('close-cycle-modal');
    }

    public function openDetail(int $cycleId): void
    {
        $cycle = ReportingCycle::with('periods')->findOrFail($cycleId);

        $this->detailCycleId = $cycle->id;
        $this->segmentStatuses = $cycle->periods
            ->mapWithKeys(fn (ReportingPeriod $period) => [
                $period->id => $period->status,
            ])
            ->all();
        $this->resetValidation();
        $this->dispatch('open-cycle-detail-modal');
    }

    public function saveStatuses(): void
    {
        Gate::authorize('reporting-admin');
        $cycle = ReportingCycle::with('periods')->findOrFail($this->detailCycleId);
        $rules = [];

        foreach ($cycle->periods as $period) {
            $rules['segmentStatuses.'.$period->id] = [
                'required',
                Rule::in(['DRAFT', 'OPEN', 'CLOSED', 'LOCKED']),
            ];
        }

        $this->validate($rules);

        DB::transaction(function () use ($cycle): void {
            foreach ($cycle->periods as $period) {
                $status = $this->segmentStatuses[$period->id];

                if ($period->status === $status) {
                    continue;
                }

                if ($status === 'DRAFT' && $period->reports()->exists()) {
                    throw ValidationException::withMessages([
                        'segmentStatuses.'.$period->id => 'Periode yang sudah berisi laporan tidak dapat dikembalikan menjadi draf.',
                    ]);
                }

                DB::table('period_histories')->insert([
                    'period_id' => $period->id,
                    'user_id' => auth()->id(),
                    'from_status' => $period->status,
                    'to_status' => $status,
                    'note' => 'Perubahan status melalui periode tahun.',
                    'created_at' => now(),
                ]);

                $period->update(['status' => $status]);
            }
        });

        session()->flash('success', 'Status jadwal pengisian berhasil diperbarui.');
        $this->dispatch('close-cycle-detail-modal');
    }

    public function render(): View
    {
        $cycles = ReportingCycle::query()
            ->with(['periods' => fn ($query) => $query->orderBy('quarter')])
            ->withCount('periods')
            ->withCount([
                'periods as open_periods_count' => fn ($query) => $query->where('status', 'OPEN'),
            ])
            ->when(
                trim($this->search) !== '',
                fn ($query) => $query->where('year', 'like', '%'.trim($this->search).'%')
            )
            ->orderByDesc('year')
            ->paginate(in_array($this->perPage, [5, 10, 25], true) ? $this->perPage : 10);

        $detailCycle = $this->detailCycleId
            ? ReportingCycle::with(['periods' => fn ($query) => $query->orderBy('quarter')])
                ->find($this->detailCycleId)
            : null;

        return view('livewire.reporting-cycle-table', [
            'cycles' => $cycles,
            'detailCycle' => $detailCycle,
        ]);
    }

    private function segments(int $year, string $frequency): array
    {
        $count = match ($frequency) {
            'MONTHLY' => 12,
            'SEMESTERLY' => 2,
            default => 4,
        };
        $monthStep = match ($frequency) {
            'MONTHLY' => 1,
            'SEMESTERLY' => 6,
            default => 3,
        };
        $segments = [];

        for ($sequence = 1; $sequence <= $count; $sequence++) {
            $segments[] = [
                'quarter' => $sequence,
                'deadline' => CarbonImmutable::create($year, $sequence * $monthStep, 1)->endOfMonth(),
            ];
        }

        return $segments;
    }
}
