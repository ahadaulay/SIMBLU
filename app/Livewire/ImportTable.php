<?php

namespace App\Livewire;

use App\Models\Indicator;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\UnitReport;
use App\Services\IndicatorCalculator;
use App\Services\ReportWorkflow;
use App\Services\WorkbookReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportTable extends Component
{
    use WithFileUploads;

    public $file;

    public string $periodId = '';

    public string $unitId = '';

    public string $indicatorId = '';

    public string $sheetName = '';

    public int $firstRow = 5;

    public int $lastRow = 5;

    public string $labelColumn = 'B';

    public array $columns = [];

    #[Locked]
    public array $previewRows = [];

    #[Locked]
    public array $sheetNames = [];

    #[Locked]
    public string $previewHash = '';

    public function boot(): void
    {
        Gate::authorize('reporting-admin');
    }

    public function updatedFile(): void
    {
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:10240']]);
        $this->sheetNames = app(WorkbookReader::class)->read($this->file->getRealPath())['sheets'];
        $this->previewRows = [];
        $this->previewHash = '';
    }

    private function definition(): array
    {
        $entry = UnitReport::where('period_id', $this->periodId)->where('unit_id', $this->unitId)->first()?->entries()->where('indicator_id', $this->indicatorId)->first();

        return $entry?->definition ?? Indicator::findOrFail($this->indicatorId)->definition();
    }

    private function fingerprint(array $definition): string
    {
        return hash('sha256', json_encode([$this->periodId, $this->unitId, $this->indicatorId, $this->sheetName, $this->firstRow, $this->lastRow, $this->labelColumn, $this->columns, $definition, hash_file('sha256', $this->file->getRealPath())]));
    }

    public function preview(WorkbookReader $reader, IndicatorCalculator $calculator): void
    {
        $this->previewRows = [];
        $this->previewHash = '';
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:10240'], 'periodId' => ['required', 'exists:reporting_periods,id'], 'unitId' => ['required', 'exists:units,id'], 'indicatorId' => ['required', 'exists:indicators,id'], 'sheetName' => ['required', 'string', 'max:100'], 'firstRow' => ['required', 'integer', 'between:1,100000'], 'lastRow' => ['required', 'integer', 'gte:firstRow', 'between:1,100000'], 'labelColumn' => ['required', 'regex:/^[A-Z]{1,3}$/'], 'columns' => ['required', 'array'], 'columns.*' => ['nullable', 'regex:/^[A-Z]{1,3}$/']]);
        if ($this->lastRow - $this->firstRow >= 500) {
            $this->addError('lastRow', 'Maksimal 500 baris per impor.');

            return;
        }
        $definition = $this->definition();
        $cells = $reader->read($this->file->getRealPath(), $this->sheetName)['cells'];
        $items = $definition['form_type'] === 'SINGLE' ? [['code' => 'single', 'label' => $definition['name']]] : $definition['items'];
        $rows = [];
        for ($number = $this->firstRow; $number <= $this->lastRow; $number++) {
            $index = $number - $this->firstRow;
            $values = [];
            foreach ($definition['fields'] as $field) {
                if ($field['type'] === 'CALCULATED') {
                    continue;
                }
                $column = $this->columns[$field['code']] ?? '';
                if ($column === '') {
                    $this->addError('columns', 'Pilih kolom sumber untuk '.$field['label']);

                    return;
                }
                $cell = $cells[$column.$number] ?? ['value' => '', 'formula' => false, 'error' => false];
                if ($cell['formula'] || $cell['error']) {
                    $this->addError('file', 'Sel '.$column.$number.' berisi formula/error. Pilih data sumber, bukan total atau rasio hasil perhitungan.');

                    return;
                }
                $value = trim($cell['value']);
                if ($field['type'] === 'DATE' && is_numeric($value)) {
                    $value = CarbonImmutable::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d');
                }
                $values[$field['code']] = $value === '' ? null : $value;
            }
            $rows[] = ['key' => $definition['form_type'] === 'DYNAMIC' ? (string) Str::uuid() : ($items[$index]['code'] ?? 'invalid'), 'label' => $definition['form_type'] === 'DYNAMIC' ? trim($cells[$this->labelColumn.$number]['value'] ?? '') : ($items[$index]['label'] ?? ''), 'values' => $values];
        }
        $this->previewRows = $calculator->normalize($definition, $rows, false);
        $this->previewHash = $this->fingerprint($definition);
    }

    public function save(ReportWorkflow $workflow): void
    {
        Gate::authorize('reporting-admin');
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:10240']]);
        if ($this->previewHash === '' || ! hash_equals($this->previewHash, $this->fingerprint($this->definition()))) {
            $this->addError('file', 'Buat pratinjau lagi setelah mengganti pilihan.');

            return;
        }
        $report = $workflow->import((int) $this->periodId, (int) $this->unitId, (int) $this->indicatorId, $this->previewRows, basename($this->file->getClientOriginalName()).' / '.$this->sheetName.' / '.$this->firstRow.'–'.$this->lastRow);
        $this->previewRows = [];
        $this->previewHash = '';
        session()->flash('success', 'Data diimpor sebagai draf laporan #'.$report->id.'. Unit meninjau dan mengajukan sebelum admin memverifikasi.');
    }

    public function render(): View
    {
        return view('livewire.import-table', ['periods' => ReportingPeriod::orderByDesc('year')->orderBy('quarter')->get(), 'units' => Unit::orderBy('name')->get(), 'indicators' => Indicator::orderBy('name')->get(), 'definition' => $this->indicatorId ? $this->definition() : null]);
    }
}
