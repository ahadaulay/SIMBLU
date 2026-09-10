<?php

namespace App\Livewire;

use App\Jobs\BuildReportingExport;
use App\Models\Indicator;
use App\Models\IndicatorAssignment;
use App\Models\RecapMetric;
use App\Models\ReportingExport;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Services\ReportRecap;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class RecapTable extends Component
{
    public array $periodIds = [];

    public array $unitIds = [];

    public array $indicatorIds = [];

    public array $metricIds = [];

    public string $grouping = 'PERIOD';

    public string $startPeriod = '';

    public string $endPeriod = '';

    public function boot(): void
    {
        Gate::authorize('reporting-access');
    }

    public function queueExport(): void
    {
        Gate::authorize('reporting-access');
        $this->validate(['periodIds' => ['required', 'array', 'min:1', 'max:80'], 'periodIds.*' => ['integer', 'exists:reporting_periods,id'], 'unitIds' => ['array'], 'indicatorIds' => ['array'], 'metricIds' => ['array'], 'grouping' => ['in:PERIOD,YEAR,TOTAL']]);
        if (ReportingExport::where('user_id', auth()->id())->whereIn('status', ['PENDING', 'PROCESSING'])->count() >= 3) {
            $this->addError('periodIds', 'Tunggu ekspor sebelumnya selesai.');

            return;
        }
        $export = ReportingExport::create(['user_id' => auth()->id(), 'filters' => ['periods' => $this->periodIds, 'units' => $this->unitIds, 'indicators' => $this->indicatorIds, 'metrics' => $this->metricIds, 'grouping' => $this->grouping, 'access_role' => auth()->user()->role, 'access_unit' => auth()->user()->unit_id]]);
        BuildReportingExport::dispatch($export->id);
        session()->flash('success', 'Permintaan ekspor masuk antrean. Gunakan Perbarui status untuk memeriksa hasil.');
    }

    public function selectRange(): void
    {
        $this->validate(['startPeriod' => ['required', 'exists:reporting_periods,id'], 'endPeriod' => ['required', 'exists:reporting_periods,id']]);
        $start = ReportingPeriod::findOrFail($this->startPeriod);
        $end = ReportingPeriod::findOrFail($this->endPeriod);
        if ($start->deadline->greaterThan($end->deadline)) {
            $this->addError('startPeriod', 'Periode awal harus sebelum periode akhir.');

            return;
        }
        $this->periodIds = ReportingPeriod::whereBetween('deadline', [
            $start->deadline,
            $end->deadline,
        ])->orderBy('deadline')->pluck('id')->all();
    }

    public function render(): View
    {
        $filters = ['periods' => $this->periodIds, 'units' => $this->unitIds, 'indicators' => $this->indicatorIds, 'metrics' => $this->metricIds, 'grouping' => $this->grouping];
        $result = $this->periodIds ? app(ReportRecap::class)->build($filters) : null;

        return view('livewire.recap-table', [
            'exports' => ReportingExport::where('user_id', auth()->id())->latest()->limit(10)->get(),
            'periods' => ReportingPeriod::with('cycle')->orderByDesc('year')->orderBy('quarter')->get(),
            'units' => Unit::when(auth()->user()->role === 'UNIT', fn ($q) => $q->whereKey(auth()->user()->unit_id))->orderBy('name')->get(),
            'indicators' => Indicator::when(auth()->user()->role === 'UNIT', fn ($q) => $q->whereIn('id', IndicatorAssignment::where('unit_id', auth()->user()->unit_id)->select('indicator_id')))->orderBy('name')->get(),
            'metrics' => RecapMetric::where('active', true)->orderBy('name')->get(), 'result' => $result, 'query' => http_build_query($filters),
        ]);
    }
}
