<?php

namespace App\Livewire;

use App\Models\IndicatorAssignment;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\UnitReport;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ReportingDashboard extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $periodId = '';

    public function boot(): void
    {
        Gate::authorize('reporting-access');
    }

    public function mount(): void
    {
        $this->periodId = (string) (ReportingPeriod::orderByDesc('year')->orderByDesc('quarter')->value('id') ?? '');
    }

    public function updatedPeriodId(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $period = ReportingPeriod::find($this->periodId);
        $unitId = auth()->user()->role === 'UNIT' ? auth()->user()->unit_id : null;
        $query = UnitReport::where('period_id', $this->periodId)->when($unitId, fn ($q) => $q->where('unit_id', $unitId));
        $counts = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();
        $assigned = IndicatorAssignment::where('period_id', $this->periodId)->when($unitId, fn ($q) => $q->where('unit_id', $unitId))->distinct()->pluck('unit_id');
        $reported = (clone $query)->pluck('unit_id');
        $missing = Unit::whereIn('id', $assigned->diff($reported))->orderBy('name')->get(['id', 'name']);

        return view('livewire.reporting-dashboard', ['period' => $period, 'periods' => ReportingPeriod::orderByDesc('year')->orderByDesc('quarter')->get(), 'counts' => $counts, 'missing' => $missing, 'records' => (clone $query)->with(['unit', 'entries', 'attachments'])->latest()->paginate(10)]);
    }
}
