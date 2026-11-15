<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\IndicatorAssignment;
use App\Models\IndicatorCategory;
use App\Models\ReportingCycle;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\UnitReport;
use App\Models\User;
use App\Services\IndicatorCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Reporting2026Seeder extends Seeder
{
    private const SOURCE_SHA256 = 'e854446b93c1277ef3d1803c15b90c0ad412e2cb9e087361db134419f2a868f1';

    private const UNIT_COUNT = 18;

    private const INDICATOR_COUNT = 66;

    private const VERIFIED_REPORT_COUNT = 54;

    private const VERIFIED_ENTRY_COUNT = 198;

    public function run(): void
    {
        $fixture = $this->loadFixture();
        $values = $this->loadValues();
        $this->validateFixture($fixture);
        $this->validateValues($values);

        DB::transaction(function () use ($fixture, $values): void {
            $admin = User::where('email', config('reporting.seed_admin.email'))->firstOrFail();
            $periods = $this->seedPeriods($admin);

            $units = [];
            $responsibleUsers = [];
            $indicators = [];

            foreach ($fixture['units'] as $unitDefinition) {
                $unit = Unit::where('code', $unitDefinition['unit_code'])
                    ->where('active', true)
                    ->firstOrFail();
                $responsible = User::where('unit_id', $unit->id)
                    ->where('role', 'UNIT')
                    ->where('active', true)
                    ->firstOrFail();
                $units[$unit->code] = $unit;
                $responsibleUsers[$unit->code] = $responsible;
                $category = IndicatorCategory::updateOrCreate(
                    ['code' => $unitDefinition['category_code']],
                    ['name' => $unitDefinition['category_name'], 'active' => true],
                );

                foreach ($unitDefinition['indicators'] as $definition) {
                    $indicator = $this->seedIndicator(
                        $admin,
                        $category,
                        $unitDefinition['sheet'],
                        $definition,
                    );
                    $indicators[$indicator->code] = $indicator->load('category');

                    foreach ($periods as $period) {
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
                    }
                }
            }

            $this->seedVerifiedReports($values, $periods, $units, $responsibleUsers, $indicators, $admin);
            $this->seedDraftReports($fixture, $periods[4], $units, $responsibleUsers, $indicators);
        });
    }

    private function loadValues(): array
    {
        $json = file_get_contents(database_path('seeders/data/reporting-2026-values.json'));

        if ($json === false) {
            throw new RuntimeException('Fixture nilai pelaporan 2026 tidak ditemukan.');
        }

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    private function loadFixture(): array
    {
        $json = file_get_contents(database_path('seeders/data/reporting-2026.json'));

        if ($json === false) {
            throw new RuntimeException('Fixture pelaporan 2026 tidak ditemukan.');
        }

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    private function validateFixture(array $fixture): void
    {
        $indicatorCount = array_sum(array_map(
            fn (array $unit): int => count($unit['indicators'] ?? []),
            $fixture['units'] ?? [],
        ));

        if (($fixture['year'] ?? null) !== 2026
            || ($fixture['frequency'] ?? null) !== 'QUARTERLY'
            || ($fixture['source_sha256'] ?? null) !== self::SOURCE_SHA256
            || count($fixture['units'] ?? []) !== self::UNIT_COUNT
            || $indicatorCount !== self::INDICATOR_COUNT) {
            throw new RuntimeException('Fixture pelaporan 2026 berubah atau tidak lengkap.');
        }
    }

    private function validateValues(array $fixture): void
    {
        $entryCount = array_sum(array_map(
            fn (array $report): int => count($report['entries'] ?? []),
            $fixture['reports'] ?? [],
        ));

        if (($fixture['year'] ?? null) !== 2026
            || ($fixture['source_sha256'] ?? null) !== self::SOURCE_SHA256
            || count($fixture['reports'] ?? []) !== self::VERIFIED_REPORT_COUNT
            || $entryCount !== self::VERIFIED_ENTRY_COUNT) {
            throw new RuntimeException('Fixture nilai pelaporan 2026 berubah atau tidak lengkap.');
        }
    }

    private function seedPeriods(User $admin): array
    {
        $cycle = ReportingCycle::updateOrCreate(
            ['year' => 2026],
            ['frequency' => 'QUARTERLY', 'created_by' => $admin->id],
        );
        $deadlines = [
            1 => '2026-03-31',
            2 => '2026-06-30',
            3 => '2026-09-30',
            4 => '2026-12-31',
        ];
        $periods = [];

        foreach ($deadlines as $quarter => $deadline) {
            $period = ReportingPeriod::firstOrCreate(
                ['year' => 2026, 'quarter' => $quarter],
                [
                    'cycle_id' => $cycle->id,
                    'deadline' => $deadline,
                    'status' => $quarter === 4 ? 'OPEN' : 'CLOSED',
                ],
            );

            $period->update([
                'cycle_id' => $cycle->id,
                'deadline' => $deadline,
            ]);
            $periods[$quarter] = $period;
        }

        return $periods;
    }

    private function seedVerifiedReports(
        array $fixture,
        array $periods,
        array $units,
        array $responsibleUsers,
        array $indicators,
        User $admin,
    ): void {
        $calculator = app(IndicatorCalculator::class);

        foreach ($fixture['reports'] as $reportDefinition) {
            $unit = $units[$reportDefinition['unit_code']] ?? null;
            $responsible = $responsibleUsers[$reportDefinition['unit_code']] ?? null;
            $period = $periods[$reportDefinition['quarter']] ?? null;

            if (! $unit || ! $responsible || ! $period || $reportDefinition['quarter'] === 4) {
                throw new RuntimeException('Unit atau periode pada fixture nilai pelaporan 2026 tidak valid.');
            }

            $report = UnitReport::updateOrCreate(
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

            foreach ($reportDefinition['entries'] as $entryDefinition) {
                $indicator = $indicators[$entryDefinition['indicator_code']] ?? null;

                if (! $indicator) {
                    throw new RuntimeException('Indikator pada fixture nilai pelaporan 2026 tidak ditemukan.');
                }

                $definition = $indicator->definition();
                $noActivity = (bool) $entryDefinition['no_activity'];
                $rows = $calculator->normalize(
                    $definition,
                    $entryDefinition['rows'],
                    true,
                    $noActivity,
                );

                $report->entries()->updateOrCreate(
                    ['indicator_id' => $indicator->id],
                    [
                        'definition' => $definition,
                        'required' => true,
                        'no_activity' => $noActivity,
                        'rows' => $rows,
                        'explanation' => $entryDefinition['explanation'] ?: null,
                        'responsible_name' => $responsible->name,
                    ],
                );
            }
        }
    }

    private function seedDraftReports(
        array $fixture,
        ReportingPeriod $period,
        array $units,
        array $responsibleUsers,
        array $indicators,
    ): void {
        foreach ($fixture['units'] as $unitDefinition) {
            $unit = $units[$unitDefinition['unit_code']];
            $responsible = $responsibleUsers[$unitDefinition['unit_code']];
            $report = UnitReport::firstOrCreate(
                ['unit_id' => $unit->id, 'period_id' => $period->id],
                [
                    'created_by' => $responsible->id,
                    'status' => 'DRAFT',
                    'version' => 1,
                ],
            );

            foreach ($unitDefinition['indicators'] as $indicatorDefinition) {
                $indicator = $indicators[$indicatorDefinition['code']];
                $definition = $indicator->definition();

                $report->entries()->firstOrCreate(
                    ['indicator_id' => $indicator->id],
                    [
                        'definition' => $definition,
                        'required' => true,
                        'no_activity' => false,
                        'rows' => $this->blankRows($definition),
                        'explanation' => null,
                        'responsible_name' => $responsible->name,
                    ],
                );
            }
        }
    }

    private function blankRows(array $definition): array
    {
        if ($definition['form_type'] === 'DYNAMIC') {
            return [];
        }

        $items = $definition['form_type'] === 'SINGLE'
            ? [['code' => 'single', 'label' => $definition['name']]]
            : $definition['items'];

        return array_map(fn (array $item): array => [
            'key' => $item['code'],
            'label' => $item['label'],
            'values' => array_fill_keys(array_column($definition['fields'], 'code'), null),
            'details' => [],
        ], $items);
    }

    private function seedIndicator(
        User $admin,
        IndicatorCategory $category,
        string $sheet,
        array $definition,
    ): Indicator {
        return Indicator::updateOrCreate(
            ['code' => $definition['code']],
            [
                'category_id' => $category->id,
                'name' => $definition['name'],
                'description' => sprintf(
                    'Sumber: sheet %s, baris %s pada DATA LAPORAN KINERJA LAYANAN 2026.xlsx.',
                    $sheet,
                    $definition['source_rows'],
                ),
                'form_type' => $definition['form_type'],
                'aggregation' => $definition['aggregation'],
                'direction' => 'NEUTRAL',
                'result_field' => $definition['result_field'],
                'denominator_field' => null,
                'ratio_scale' => 1,
                'requires_attachment' => false,
                'requires_explanation' => false,
                'active' => true,
                'version' => 1,
                'fields' => $definition['fields'],
                'items' => $definition['items'],
                'created_by' => $admin->id,
            ],
        );
    }
}
