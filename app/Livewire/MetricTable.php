<?php

namespace App\Livewire;

use App\Models\Indicator;
use App\Models\RecapMetric;
use App\Services\IndicatorCalculator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class MetricTable extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Locked]
    public ?int $editingId = null;

    public string $search = '';

    public array $form = ['indicator_id' => '', 'code' => '', 'name' => '', 'result_field' => '', 'aggregation' => 'SUM', 'denominator_field' => '', 'ratio_scale' => 1, 'filter_field' => '', 'combine_units' => false, 'active' => true];

    public string $filterValues = '';

    #[Locked]
    public ?int $indicatorId = null;

    public bool $createOnLoad = false;

    public function mount(): void
    {
        $indicatorId = request()->integer('indicator');
        $this->indicatorId = Indicator::whereKey($indicatorId)->exists() ? $indicatorId : null;
        $this->createOnLoad = $this->indicatorId !== null && request()->boolean('create');
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
        $this->reset(['form', 'editingId', 'filterValues']);
        $this->resetValidation();
        $this->dispatch('open-metric-modal');
    }

    public function openContextCreateModal(): void
    {
        $this->openCreateModal();
        $this->form['indicator_id'] = $this->indicatorId;
        $this->createOnLoad = false;
    }

    public function openEditModal(int $id): void
    {
        $record = RecapMetric::findOrFail($id);
        $this->editingId = $id;
        $this->form = $record->only(array_keys($this->form));
        $this->filterValues = implode("\n", $record->filter_values);
        $this->resetValidation();
        $this->dispatch('open-metric-modal');
    }

    public function save(IndicatorCalculator $calculator): void
    {
        Gate::authorize('reporting-admin');
        $data = $this->validate([
            'form.indicator_id' => ['required', 'exists:indicators,id'], 'form.code' => ['required', 'alpha_dash:ascii', 'max:30', Rule::unique('recap_metrics', 'code')->ignore($this->editingId)], 'form.name' => ['required', 'string', 'max:255'],
            'form.result_field' => ['required', 'string', 'max:30'], 'form.aggregation' => ['required', Rule::in(array_keys(config('reporting.aggregations')))], 'form.denominator_field' => ['nullable', 'string', 'max:30'], 'form.ratio_scale' => ['required', Rule::in([1, 100])],
            'form.filter_field' => ['nullable', 'string', 'max:30'], 'form.combine_units' => ['boolean'], 'form.active' => ['boolean'], 'filterValues' => ['nullable', 'string', 'max:5000'],
        ])['form'];
        $indicator = Indicator::findOrFail($data['indicator_id']);
        $calculator->validateDefinition($indicator->fields, $indicator->items, $indicator->form_type, $data['result_field'], $data['aggregation'], $data['denominator_field'] ?: null);
        $values = array_values(array_filter(array_map('trim', preg_split('/\R/', $this->filterValues)), fn ($v) => $v !== ''));
        if ($data['filter_field'] && (! in_array($data['filter_field'], array_column($indicator->fields, 'code'), true) || $values === [])) {
            $this->addError('filterValues', 'Pilih field sumber dan isi nilai penyaring satu per baris.');

            return;
        }
        $record = $this->editingId ? RecapMetric::findOrFail($this->editingId) : new RecapMetric;
        $record->fill($data + ['indicator_version' => $indicator->version, 'filter_values' => $values])->save();
        $this->dispatch('close-metric-modal');
        session()->flash('success', 'Pemetaan rekap disimpan untuk versi indikator terpilih.');
    }

    public function render(): View
    {
        return view('livewire.metric-table', [
            'records' => RecapMetric::with('indicator')
                ->when($this->indicatorId, fn ($query) => $query->where('indicator_id', $this->indicatorId))
                ->where('name', 'like', '%'.trim($this->search).'%')
                ->orderBy('name')
                ->paginate(10),
            'indicators' => Indicator::orderBy('name')->get(),
            'source' => Indicator::find($this->form['indicator_id']),
            'selectedIndicator' => $this->indicatorId ? Indicator::find($this->indicatorId) : null,
        ]);
    }
}
