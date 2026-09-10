<?php

namespace App\Livewire;

use App\Models\Indicator;
use App\Models\IndicatorCategory;
use App\Services\IndicatorCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class IndicatorTable extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public int $editingVersion = 0;

    public string $search = '';

    public int $perPage = 10;

    public string $sortDirection = 'asc';

    public array $form = ['category_id' => '', 'code' => '', 'name' => '', 'description' => '', 'form_type' => 'SINGLE', 'aggregation' => 'SUM', 'direction' => 'NEUTRAL', 'result_field' => 'amount', 'denominator_field' => '', 'ratio_scale' => 1, 'requires_attachment' => false, 'requires_explanation' => false, 'active' => true];

    public array $fields = [['code' => 'amount', 'label' => 'Jumlah', 'type' => 'NUMBER', 'unit' => '', 'required' => true, 'decimals' => 0, 'min' => 0, 'max' => '', 'options' => '', 'operation' => 'SUM', 'operands' => '']];

    public array $items = [];

    #[Locked]
    public ?int $categoryId = null;

    public bool $createOnLoad = false;

    public function mount(): void
    {
        $categoryId = request()->integer('category');
        $this->categoryId = IndicatorCategory::whereKey($categoryId)->exists() ? $categoryId : null;
        $this->createOnLoad = $this->categoryId !== null && request()->boolean('create');
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
        $this->validateOnly('perPage', ['perPage' => [Rule::in([5, 10, 25])]]);
        $this->resetPage();
    }

    public function sortBy(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'editingVersion', 'form', 'fields', 'items']);
        $this->resetValidation();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->dispatch('open-indicator-modal');
    }

    public function openContextCreateModal(): void
    {
        $this->openCreateModal();
        $this->form['category_id'] = $this->categoryId;
        $this->createOnLoad = false;
    }

    public function openEditModal(int $id): void
    {
        $indicator = Indicator::findOrFail($id);
        $this->editingId = $id;
        $this->editingVersion = $indicator->version;
        $this->form = $indicator->only(array_keys($this->form));
        $this->fields = $indicator->fields;
        $this->items = $indicator->items;
        $this->resetValidation();
        $this->dispatch('open-indicator-modal');
    }

    public function addField(): void
    {
        if (count($this->fields) < 30) {
            $this->fields[] = ['code' => '', 'label' => '', 'type' => 'NUMBER', 'unit' => '', 'required' => true, 'decimals' => 0, 'min' => '', 'max' => '', 'options' => '', 'operation' => 'SUM', 'operands' => ''];
        }
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }

    public function addItem(): void
    {
        if (count($this->items) < 300) {
            $this->items[] = ['code' => '', 'label' => ''];
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(IndicatorCalculator $calculator): void
    {
        Gate::authorize('reporting-admin');
        $data = $this->validate([
            'form.category_id' => ['required', Rule::exists('indicator_categories', 'id')],
            'form.code' => ['required', 'alpha_dash:ascii', 'max:30', Rule::unique('indicators', 'code')->ignore($this->editingId)],
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string', 'max:5000'],
            'form.form_type' => ['required', Rule::in(['SINGLE', 'FIXED', 'DYNAMIC'])],
            'form.aggregation' => ['required', Rule::in(array_keys(config('reporting.aggregations')))],
            'form.direction' => ['required', Rule::in(['UP_GOOD', 'DOWN_GOOD', 'NEUTRAL'])],
            'form.result_field' => ['required', 'string', 'max:30'],
            'form.denominator_field' => ['nullable', 'string', 'max:30'],
            'form.ratio_scale' => ['required', Rule::in([1, 100])],
            'form.requires_attachment' => ['boolean'], 'form.requires_explanation' => ['boolean'], 'form.active' => ['boolean'],
        ])['form'];
        $calculator->validateDefinition($this->fields, $this->items, $data['form_type'], $data['result_field'], $data['aggregation'], $data['denominator_field'] ?: null);
        DB::transaction(function () use ($data): void {
            $indicator = $this->editingId ? Indicator::lockForUpdate()->findOrFail($this->editingId) : new Indicator;
            if ($indicator->exists && $indicator->version !== $this->editingVersion) {
                throw ValidationException::withMessages(['form.name' => 'Formulir diubah admin lain. Muat ulang sebelum menyimpan.']);
            }
            $indicator->fill($data + ['fields' => $this->fields, 'items' => $this->items]);
            $indicator->version = $indicator->exists ? $indicator->version + 1 : 1;
            $indicator->created_by ??= auth()->id();
            $indicator->save();
        });
        $this->dispatch('close-indicator-modal');
        $this->resetForm();
        session()->flash('success', 'Indikator disimpan. Laporan yang sudah dibuat tetap memakai versi sebelumnya.');
    }

    public function render(): View
    {
        return view('livewire.indicator-table', [
            'records' => Indicator::with('category')
                ->when($this->categoryId, fn ($query) => $query->where('category_id', $this->categoryId))
                ->where(fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%')->orWhere('code', 'like', '%'.trim($this->search).'%'))
                ->orderBy('name', $this->sortDirection === 'desc' ? 'desc' : 'asc')
                ->paginate(in_array($this->perPage, [5, 10, 25]) ? $this->perPage : 10),
            'categories' => IndicatorCategory::orderBy('name')->get(),
            'selectedCategory' => $this->categoryId ? IndicatorCategory::find($this->categoryId) : null,
        ]);
    }
}
