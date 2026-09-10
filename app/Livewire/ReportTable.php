<?php

namespace App\Livewire;

use App\Models\ReportingPeriod;
use App\Models\UnitReport;
use App\Services\ReportWorkflow;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ReportTable extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $search = '';

    public string $status = '';

    public string $periodId = '';

    public function boot(): void
    {
        Gate::authorize('reporting-access');
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'periodId'])) {
            $this->resetPage();
        }
    }

    public function start(ReportWorkflow $workflow): void
    {
        $this->validate(['periodId' => ['required', 'integer', 'exists:reporting_periods,id']]);
        $report = $workflow->start((int) $this->periodId, auth()->user());
        $this->redirectRoute('reports.show', $report);
    }

    public function render(): View
    {
        $query = UnitReport::with(['period', 'unit', 'entries', 'attachments'])
            ->when(auth()->user()->role === 'UNIT', fn ($q) => $q->where('unit_id', auth()->user()->unit_id))
            ->when($this->periodId !== '', fn ($q) => $q->where('period_id', $this->periodId))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->whereHas('unit', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'));

        return view('livewire.report-table', ['records' => $query->latest()->paginate(10), 'periods' => ReportingPeriod::orderByDesc('year')->orderByDesc('quarter')->get()]);
    }
}
