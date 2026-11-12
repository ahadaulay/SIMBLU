<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\IndicatorAssignment;
use App\Models\ReportingCycle;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\UnitReport;
use App\Models\User;
use App\Services\IndicatorCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Reporting2025DummySeeder extends Seeder
{
    public function run(): void
    {
        $fixture = $this->loadFixture();

        DB::transaction(function () use ($fixture): void {
            $admin = User::where('email', config('reporting.seed_admin.email'))->firstOrFail();
            $periods = $this->seedPeriods($admin);

            foreach ($fixture['units'] as $unitDefinition) {
                $unit = Unit::where('code', $unitDefinition['unit_code'])->where('active', true)->firstOrFail();
                $responsible = User::where('unit_id', $unit->id)
                    ->where('role', 'UNIT')
                    ->where('active', true)
                    ->firstOrFail();
                $indicators = Indicator::with('category')
                    ->whereIn('code', array_column($unitDefinition['indicators'], 'code'))
                    ->get()
                    ->keyBy('code');

                if ($indicators->count() !== count($unitDefinition['indicators'])) {
                    throw new RuntimeException('Jalankan Reporting2026Seeder sebelum Reporting2025DummySeeder.');
                }

                foreach ($periods as $quarter => $period) {
                    $report = UnitReport::firstOrCreate(
                        ['unit_id' => $unit->id, 'period_id' => $period->id],
                        [
                            'created_by' => $responsible->id,
                            'verified_by' => $admin->id,
                            'status' => 'VERIFIED',
                            'version' => 1,
                            'submitted_at' => $period->deadline->format('Y-m-d').' 09:00:00',
                            'verified_at' => $period->deadline->format('Y-m-d').' 10:00:00',
                        ],
                    );

                    foreach ($unitDefinition['indicators'] as $indicatorDefinition) {
                        $indicator = $indicators[$indicatorDefinition['code']];

                        IndicatorAssignment::updateOrCreate(
                            [
                                'period_id' => $period->id,
                                'unit_id' => $unit->id,
                                'indicator_id' => $indicator->id,
                            ],
                            [
                                'responsible_user_id' => $responsible->id,
                                'required' => true,
                                'assigned_by' => $admin->id,
                            ],
                        );

                        $definition = $this->definitionFor2025($indicator->definition());
                        $report->entries()->firstOrCreate(
                            ['indicator_id' => $indicator->id],
                            [
                                'definition' => $definition,
                                'required' => true,
                                'no_activity' => false,
                                'rows' => $this->dummyRows($definition, $quarter),
                                'explanation' => 'Data dummy lengkap Triwulan '.$quarter.' Tahun 2025.',
                                'responsible_name' => $responsible->name,
                            ],
                        );
                    }
                }
            }
        });
    }

    private function loadFixture(): array
    {
        $json = file_get_contents(database_path('seeders/data/reporting-2026.json'));

        if ($json === false) {
            throw new RuntimeException('Fixture pelaporan 2026 tidak ditemukan.');
        }

        $fixture = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $indicatorCount = array_sum(array_map(
            fn (array $unit): int => count($unit['indicators'] ?? []),
            $fixture['units'] ?? [],
        ));

        if (($fixture['year'] ?? null) !== 2026 || $indicatorCount !== 66) {
            throw new RuntimeException('Fixture pelaporan 2026 tidak lengkap.');
        }

        return $fixture;
    }

    private function seedPeriods(User $admin): array
    {
        $cycle = ReportingCycle::updateOrCreate(
            ['year' => 2025],
            ['frequency' => 'QUARTERLY', 'created_by' => $admin->id],
        );
        $deadlines = [
            1 => '2025-03-31',
            2 => '2025-06-30',
            3 => '2025-09-30',
            4 => '2025-12-31',
        ];
        $periods = [];

        foreach ($deadlines as $quarter => $deadline) {
            $period = ReportingPeriod::firstOrCreate(
                ['year' => 2025, 'quarter' => $quarter],
                [
                    'cycle_id' => $cycle->id,
                    'deadline' => $deadline,
                    'status' => 'CLOSED',
                ],
            );
            $period->update(['cycle_id' => $cycle->id, 'deadline' => $deadline]);
            $periods[$quarter] = $period;
        }

        return $periods;
    }

    private function definitionFor2025(array $definition): array
    {
        foreach (['name', 'description', 'category'] as $key) {
            if (isset($definition[$key]) && is_string($definition[$key])) {
                $definition[$key] = str_replace('2026', '2025', $definition[$key]);
            }
        }

        return $definition;
    }

    private function dummyRows(array $definition, int $quarter): array
    {
        $calculator = app(IndicatorCalculator::class);
        $items = $definition['form_type'] === 'DYNAMIC'
            ? [['code' => 'dummy_'.$quarter, 'label' => 'Data dummy Triwulan '.$quarter]]
            : $definition['items'];
        $rows = [];

        foreach ($items as $rowIndex => $item) {
            $values = [];

            foreach ($definition['fields'] as $fieldIndex => $field) {
                $values[$field['code']] = match ($field['type']) {
                    'NUMBER' => $quarter * 10 + $rowIndex + $fieldIndex + 1,
                    'TEXT' => 'Uraian dummy '.$item['label'].' Triwulan '.$quarter,
                    'CALCULATED' => null,
                    default => throw new RuntimeException('Tipe field fixture tidak didukung: '.$field['type']),
                };
            }

            $rows[] = [
                'key' => $item['code'],
                'label' => $item['label'],
                'values' => $values,
            ];
        }

        $rows = $calculator->normalize($definition, $rows, false);
        $detailField = $calculator->detailField($definition);

        if (! $detailField) {
            throw new RuntimeException('Field hasil indikator harus berupa angka.');
        }

        foreach ($rows as &$row) {
            if ($detailField['type'] === 'CALCULATED') {
                $row['details'] = array_map(fn (string $code) => [
                    'name' => ucfirst($code).' - '.$row['label'],
                    'amount' => $row['values'][$code],
                ], ['lokal', 'regional', 'nasional', 'internasional']);
            } else {
                $row['details'] = [[
                    'name' => 'Detail dummy - '.$row['label'],
                    'amount' => $row['values'][$detailField['code']],
                ]];
            }
        }
        unset($row);

        return $calculator->normalize($definition, $rows, true);
    }
}
