<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IndicatorCalculator
{
    public function validateDefinition(array $fields, array $items, string $type, string $result, string $aggregation, ?string $denominator): void
    {
        Validator::make(['fields' => $fields, 'items' => $items], [
            'fields' => ['required', 'array', 'min:1', 'max:30'],
            'fields.*.code' => ['required', 'regex:/^[a-z][a-z0-9_]{0,29}$/', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:150'],
            'fields.*.type' => ['required', Rule::in(['NUMBER', 'TEXT', 'DATE', 'CHOICE', 'BOOLEAN', 'CALCULATED'])],
            'fields.*.unit' => ['nullable', 'string', 'max:50'],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.decimals' => ['required', 'integer', 'between:0,6'],
            'fields.*.min' => ['nullable', 'numeric', 'between:-999999999999,999999999999'],
            'fields.*.max' => ['nullable', 'numeric', 'between:-999999999999,999999999999'],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
            'fields.*.operation' => ['required', Rule::in(['SUM', 'DIFFERENCE', 'PRODUCT', 'RATIO', 'PERCENT'])],
            'fields.*.operands' => ['nullable', 'string', 'max:1000'],
            'items' => ['array', 'max:300'],
            'items.*.code' => ['required', 'regex:/^[a-z][a-z0-9_]{0,29}$/', 'distinct'],
            'items.*.label' => ['required', 'string', 'max:200'],
        ])->validate();
        $known = [];
        foreach ($fields as $index => $field) {
            if ($field['type'] === 'CALCULATED') {
                $operands = $this->operands($field);
                if (count($operands) < 2 || array_diff($operands, $known)) {
                    throw ValidationException::withMessages(['fields.'.$index.'.operands' => 'Gunakan minimal dua kode field angka sebelumnya.']);
                }
                if (in_array($field['operation'], ['RATIO', 'PERCENT', 'DIFFERENCE']) && count($operands) !== 2) {
                    throw ValidationException::withMessages(['fields.'.$index.'.operands' => 'Operasi ini memerlukan tepat dua field.']);
                }
            }
            if (in_array($field['type'], ['NUMBER', 'CALCULATED'])) {
                $known[] = $field['code'];
            }
            if ($field['type'] === 'CHOICE' && $this->options($field) === []) {
                throw ValidationException::withMessages(['fields.'.$index.'.options' => 'Isi pilihan, satu pilihan per baris.']);
            }
            if (is_numeric($field['min'] ?? null) && is_numeric($field['max'] ?? null) && $field['min'] > $field['max']) {
                throw ValidationException::withMessages(['fields.'.$index.'.max' => 'Maksimum tidak boleh kurang dari minimum.']);
            }
        }
        if (! in_array($result, array_column($fields, 'code'), true) || (! in_array($aggregation, ['DETAIL', 'DISTINCT']) && ! in_array($result, $known, true))) {
            throw ValidationException::withMessages(['result_field' => 'Pilih field hasil yang sesuai metode rekap.']);
        }
        if ($aggregation === 'RATIO' && ! in_array($denominator, $known, true)) {
            throw ValidationException::withMessages(['denominator_field' => 'Pilih field angka sebagai penyebut.']);
        }
        if ($type === 'FIXED' && $items === []) {
            throw ValidationException::withMessages(['items' => 'Tabel tetap memerlukan minimal satu butir.']);
        }
    }

    public function options(array $field): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $field['options'] ?? '')))));
    }

    public function detailField(array $definition): ?array
    {
        foreach ($definition['fields'] as $field) {
            if ($field['code'] === $definition['result_field']
                && in_array($field['type'], ['NUMBER', 'CALCULATED'], true)) {
                return $field;
            }
        }

        return null;
    }

    public function fieldValue(array $field, array $values): mixed
    {
        if ($field['type'] !== 'CALCULATED') {
            return $values[$field['code']] ?? null;
        }

        $inputs = array_map(fn ($key) => $values[$key] ?? null, $this->operands($field));

        if (count(array_filter($inputs, 'is_numeric')) !== count($inputs)) {
            return null;
        }

        $inputs = array_map('floatval', $inputs);

        return match ($field['operation']) {
            'SUM' => array_sum($inputs),
            'DIFFERENCE' => $inputs[0] - $inputs[1],
            'PRODUCT' => array_product($inputs),
            'RATIO', 'PERCENT' => $inputs[1] == 0
                ? null
                : $inputs[0] / $inputs[1] * ($field['operation'] === 'PERCENT' ? 100 : 1),
        };
    }

    private function operands(array $field): array
    {
        return array_map('trim', explode(',', $field['operands'] ?? ''));
    }

    public function normalize(array $definition, array $rows, bool $complete, bool $noActivity = false): array
    {
        $dynamic = $definition['form_type'] === 'DYNAMIC';
        if ($noActivity) {
            if (! $dynamic || count($rows) !== 0) {
                throw ValidationException::withMessages(['rows' => 'Tanpa kegiatan hanya berlaku untuk tabel dinamis kosong.']);
            }

            return [];
        }
        $expected = $definition['form_type'] === 'SINGLE' ? [['code' => 'single', 'label' => $definition['name']]] : $definition['items'];
        if (! $dynamic && array_column($rows, 'key') !== array_column($expected, 'code')) {
            throw ValidationException::withMessages(['rows' => 'Baris formulir tetap tidak boleh diubah. Muat ulang laporan.']);
        }
        Validator::make(['rows' => $rows], [
            'rows' => ['array', 'max:500', $complete ? 'min:1' : 'min:0'],
            'rows.*.key' => ['required', 'string', 'max:50', 'distinct'],
            'rows.*.label' => [$complete ? 'required' : 'nullable', 'string', 'max:255'],
            'rows.*.values' => ['present', 'array'],
            'rows.*.details' => ['sometimes', 'array', 'max:500'],
            'rows.*.details.*.name' => [$complete ? 'required' : 'nullable', 'string', 'max:255'],
            'rows.*.details.*.amount' => [$complete ? 'required' : 'nullable', 'numeric', 'between:0,999999999999'],
        ])->validate();
        $normalized = [];
        foreach ($rows as $index => $row) {
            $values = [];
            foreach ($definition['fields'] as $field) {
                $code = $field['code'];
                $value = $row['values'][$code] ?? null;
                if ($value === '') {
                    $value = null;
                }
                if ($field['type'] === 'CALCULATED') {
                    $value = $this->fieldValue($field, $values);
                    if ($value === null && $complete) {
                        throw ValidationException::withMessages(['rows.'.$index.'.values.'.$code => 'Hasil tidak dapat dihitung. Periksa seluruh nilai sumber.']);
                    }
                }
                $rules = [$complete && $field['required'] ? 'required' : 'nullable'];
                $rules = array_merge($rules, match ($field['type']) {
                    'NUMBER', 'CALCULATED' => ['numeric', 'between:-999999999999,999999999999'],
                    'DATE' => ['date_format:Y-m-d'],
                    'BOOLEAN' => ['boolean'],
                    'CHOICE' => ['string', Rule::in($this->options($field))],
                    default => ['string', 'max:5000'],
                });
                if (in_array($field['type'], ['NUMBER', 'CALCULATED'])) {
                    foreach (['min', 'max'] as $bound) {
                        if (is_numeric($field[$bound] ?? null)) {
                            $rules[] = $bound.':'.$field[$bound];
                        }
                    }
                }
                Validator::make(['rows' => [$index => ['values' => [$code => $value]]]], ['rows.'.$index.'.values.'.$code => $rules])->validate();
                $values[$code] = $value !== null && in_array($field['type'], ['NUMBER', 'CALCULATED']) ? round((float) $value, (int) $field['decimals']) : $value;
            }
            $details = array_map(fn (array $detail) => [
                'name' => trim((string) ($detail['name'] ?? '')),
                'amount' => ($detail['amount'] ?? '') === '' ? null : (float) $detail['amount'],
            ], $row['details'] ?? []);

            if ($complete && $details !== []) {
                $detailField = $this->detailField($definition);
                $target = $detailField ? ($values[$detailField['code']] ?? null) : null;
                $total = array_sum(array_column($details, 'amount'));
                $decimals = (int) ($detailField['decimals'] ?? 2);

                if (! is_numeric($target) || round($total, $decimals) !== round((float) $target, $decimals)) {
                    throw ValidationException::withMessages([
                        'rows.'.$index.'.details' => 'Total detail harus sama dengan nilai utama pada uraian.',
                    ]);
                }
            }

            $normalized[] = [
                'key' => $row['key'],
                'label' => $dynamic ? $row['label'] : $expected[$index]['label'],
                'values' => $values,
                'details' => $details,
            ];
        }

        return $normalized;
    }

    public function aggregate(array $definition, array $periodRows): mixed
    {
        ksort($periodRows);
        if ($definition['aggregation'] === 'LAST') {
            $periodRows = $periodRows ? [end($periodRows)] : [];
        }
        $rows = array_merge([], ...array_values($periodRows));
        $values = array_values(array_filter(array_column(array_column($rows, 'values'), $definition['result_field']), fn ($value) => $value !== null && $value !== ''));
        if ($definition['aggregation'] === 'DETAIL') {
            return null;
        }
        if ($values === []) {
            return null;
        }

        return match ($definition['aggregation']) {
            'DISTINCT' => count(array_unique(array_map(fn ($value) => trim((string) $value), $values))),
            'MIN' => min($this->periodTotals($definition, $periodRows)),
            'MAX' => max($this->periodTotals($definition, $periodRows)),
            'AVERAGE' => $this->averagePeriods($definition, $periodRows),
            'RATIO' => $this->ratio($definition, $rows, $values),
            default => array_sum($values),
        };
    }

    private function ratio(array $definition, array $rows, array $values): ?float
    {
        $denominator = array_sum(array_column(array_column($rows, 'values'), $definition['denominator_field']));

        return $denominator == 0 ? null : array_sum($values) / $denominator * $definition['ratio_scale'];
    }

    private function averagePeriods(array $definition, array $periodRows): ?float
    {
        $totals = $this->periodTotals($definition, $periodRows);

        return $totals === [] ? null : array_sum($totals) / count($totals);
    }

    private function periodTotals(array $definition, array $periodRows): array
    {
        $totals = [];
        foreach ($periodRows as $rows) {
            $values = array_filter(array_column(array_column($rows, 'values'), $definition['result_field']), fn ($value) => $value !== null && $value !== '');
            $totals[] = array_sum($values);
        }

        return $totals;
    }
}
