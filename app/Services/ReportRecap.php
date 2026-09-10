<?php

namespace App\Services;

use App\Models\IndicatorAssignment;
use App\Models\RecapMetric;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\UnitReport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReportRecap
{
    public function __construct(private IndicatorCalculator $calculator) {}

    public function build(array $filters): array
    {
        Gate::authorize('reporting-access');
        $filters = Validator::make($filters, [
            'periods' => ['required', 'array', 'min:1', 'max:80'], 'periods.*' => ['required', 'integer', 'distinct', 'exists:reporting_periods,id'],
            'units' => ['present', 'array', 'max:300'], 'units.*' => ['integer', 'distinct', 'exists:units,id'],
            'indicators' => ['present', 'array', 'max:300'], 'indicators.*' => ['integer', 'distinct', 'exists:indicators,id'],
            'grouping' => ['required', Rule::in(['PERIOD', 'YEAR', 'TOTAL'])],
            'metrics' => ['sometimes', 'array', 'max:100'], 'metrics.*' => ['integer', 'distinct', 'exists:recap_metrics,id'],
        ])->validate();
        $metrics = empty($filters['metrics']) ? collect() : RecapMetric::whereIn('id', $filters['metrics'])->get();
        $unitIds = auth()->user()->role === 'UNIT' ? [auth()->user()->unit_id] : $filters['units'];
        $periods = ReportingPeriod::with('cycle')->whereIn('id', $filters['periods'])->orderBy('year')->orderBy('quarter')->get();
        $reports = UnitReport::with(['period', 'unit', 'entries'])->whereIn('period_id', $filters['periods'])->where('status', 'VERIFIED')->when($unitIds, fn ($q) => $q->whereIn('unit_id', $unitIds))->get();
        $previousPeriods = ReportingPeriod::where(function ($query) use ($periods): void {
            foreach ($periods as $period) {
                $query->orWhere(fn ($q) => $q
                    ->where('year', $period->year - 1)
                    ->where('quarter', $period->quarter)
                    ->when(
                        $period->cycle,
                        fn ($periodQuery) => $periodQuery->whereHas(
                            'cycle',
                            fn ($cycleQuery) => $cycleQuery->where('frequency', $period->cycle->frequency)
                        )
                    ));
            }
        })->with('cycle')->get();
        $previousReports = UnitReport::with(['period.cycle', 'entries'])->whereIn('period_id', $previousPeriods->pluck('id'))->where('status', 'VERIFIED')->when($unitIds, fn ($q) => $q->whereIn('unit_id', $unitIds))->get()->keyBy(fn ($report) => $report->unit_id.':'.sprintf('%04d-%d', $report->period->year, $report->period->quarter));
        $buckets = [];
        $incompatible = [];
        foreach ($reports as $report) {
            $entries = [];
            foreach ($report->entries as $original) {
                if ($metrics->isEmpty()) {
                    $entries[] = [$original, null];

                    continue;
                }
                foreach ($metrics->where('indicator_id', $original->indicator_id) as $metric) {
                    if ($metric->indicator_version !== $original->definition['version']) {
                        $incompatible[] = $report->unit->name.' / '.$report->period->label.' / '.$metric->name;

                        continue;
                    }
                    $entry = clone $original;
                    $entry->definition = $metric->definition($original->definition);
                    $entry->rows = $metric->rows($original->rows);
                    $entry->no_activity = $original->no_activity || ($original->rows !== [] && $entry->rows === []);
                    $entries[] = [$entry, $metric];
                }
            }
            foreach ($entries as [$entry, $metric]) {
                $hasValues = collect($entry->rows)->contains(fn ($row) => collect($row['values'])->contains(fn ($value) => $value !== null && $value !== ''));
                if (! $entry->no_activity && ! $hasValues) {
                    continue;
                }
                if ($filters['indicators'] && ! in_array($entry->indicator_id, $filters['indicators'])) {
                    continue;
                }
                $definition = $entry->definition;
                $group = match ($filters['grouping']) {
                    'YEAR' => (string) $report->period->year, 'PERIOD' => $report->period->label, default => 'Seluruh periode pilihan'
                };
                // Keep different definitions separate; historical formulas must not be mixed.
                $key = ($metric?->combine_units ? 'institution' : $report->unit_id).':'.$definition['code'].':'.$definition['version'].':'.$group;
                $bucket = $buckets[$key] ?? ['unit' => $metric?->combine_units ? 'Gabungan unit terpilih' : $report->unit->name, 'indicator' => $definition['name'], 'code' => $definition['code'], 'category' => $definition['category'], 'version' => $definition['version'], 'group' => $group, 'definition' => $definition, 'periodRows' => [], 'previousRows' => [], 'previousComplete' => true, 'previousEmpty' => true, 'sources' => [], 'empty' => true];
                $periodKey = sprintf('%04d-%d', $report->period->year, $report->period->quarter);
                $previousKey = sprintf('%04d-%d', $report->period->year - 1, $report->period->quarter);
                $previousEntry = $previousReports->get($report->unit_id.':'.$previousKey)?->entries->firstWhere('indicator_id', $entry->indicator_id);
                if ($previousEntry && $previousEntry->definition['version'] === $definition['version']) {
                    $previousRows = $metric ? $metric->rows($previousEntry->rows) : $previousEntry->rows;
                    $bucket['previousRows'][$previousKey] = array_merge($bucket['previousRows'][$previousKey] ?? [], $previousRows);
                    $bucket['previousEmpty'] = $bucket['previousEmpty'] && ($previousEntry->no_activity || ($metric && $previousEntry->rows !== [] && $previousRows === []));
                } else {
                    $bucket['previousComplete'] = false;
                }
                $bucket['periodRows'][$periodKey] = array_merge($bucket['periodRows'][$periodKey] ?? [], $entry->rows);
                $bucket['sources'][] = ['id' => $report->id, 'unit' => $report->unit->name, 'period' => $report->period->label, 'period_key' => $periodKey, 'explanation' => $entry->explanation, 'responsible_name' => $entry->responsible_name, 'no_activity' => $entry->no_activity, 'rows' => $entry->rows];
                $bucket['empty'] = $bucket['empty'] && $entry->no_activity;
                $buckets[$key] = $bucket;
            }
        }
        foreach ($buckets as &$bucket) {
            $bucket['value'] = $this->calculator->aggregate($bucket['definition'], $bucket['periodRows']);
            if ($bucket['empty'] && in_array($bucket['definition']['aggregation'], ['SUM', 'DISTINCT', 'AVERAGE', 'MIN', 'MAX'])) {
                $bucket['value'] = 0;
            }
            $bucket['previous_value'] = $bucket['previousComplete'] ? $this->calculator->aggregate($bucket['definition'], $bucket['previousRows']) : null;
            if ($bucket['previousComplete'] && $bucket['previousEmpty'] && in_array($bucket['definition']['aggregation'], ['SUM', 'DISTINCT', 'AVERAGE', 'MIN', 'MAX'])) {
                $bucket['previous_value'] = 0;
            }
            $bucket['condition'] = 'Tidak dapat dibandingkan';
            $bucket['change_percent'] = null;
            $bucket['performance'] = 'Netral';
            if ($bucket['value'] !== null && $bucket['previous_value'] !== null) {
                $difference = round($bucket['value'] - $bucket['previous_value'], 6);
                $bucket['condition'] = $difference === 0.0 ? 'SAMA' : ($difference > 0 ? 'NAIK' : 'TURUN');
                $bucket['change_percent'] = $bucket['previous_value'] == 0 ? null : $difference / abs($bucket['previous_value']) * 100;
                if ($difference != 0 && $bucket['definition']['direction'] !== 'NEUTRAL') {
                    $better = ($difference > 0) === ($bucket['definition']['direction'] === 'UP_GOOD');
                    $bucket['performance'] = $better ? 'Membaik' : 'Menurun';
                }
            }
            $bucket['period_count'] = count($bucket['periodRows']);
            ksort($bucket['periodRows']);
            $bucket['latest_period'] = array_key_last($bucket['periodRows']);
            unset($bucket['periodRows'], $bucket['previousRows']);
        }
        unset($bucket);
        $assignments = IndicatorAssignment::with(['unit', 'period', 'indicator'])->whereIn('period_id', $filters['periods'])->when($unitIds, fn ($q) => $q->whereIn('unit_id', $unitIds))->when($filters['indicators'], fn ($q) => $q->whereIn('indicator_id', $filters['indicators']))->when($metrics->isNotEmpty(), fn ($q) => $q->whereIn('indicator_id', $metrics->pluck('indicator_id')))->get();
        $verifiedPairs = $reports->map(fn ($report) => $report->unit_id.':'.$report->period_id)->all();
        $missing = $assignments->reject(fn ($assignment) => in_array($assignment->unit_id.':'.$assignment->period_id, $verifiedPairs, true))->map(fn ($a) => ['unit' => $a->unit->name, 'period' => $a->period->label, 'indicator' => $a->indicator->name])->values()->all();

        $availableUnits = Unit::query()
            ->when($unitIds, fn ($query) => $query->whereIn('id', $unitIds))
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return ['rows' => array_values($buckets), 'missing' => $missing, 'incompatible' => $incompatible, 'periods' => $periods->map->label->all(), 'units' => $availableUnits, 'grouping' => $filters['grouping'], 'generated_at' => now()->format('d/m/Y H:i'), 'filters' => $filters];
    }
}
