<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\IndicatorAssignment;
use App\Models\IndicatorCategory;
use App\Models\ReportingCycle;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Reporting2026Seeder extends Seeder
{
    private const SOURCE_SHA256 = 'e854446b93c1277ef3d1803c15b90c0ad412e2cb9e087361db134419f2a868f1';

    private const UNIT_COUNT = 18;

    private const INDICATOR_COUNT = 66;

    public function run(): void
    {
        $fixture = $this->loadFixture();
        $this->validateFixture($fixture);

        DB::transaction(function () use ($fixture): void {
            $admin = User::where('email', config('reporting.seed_admin.email'))->firstOrFail();
            $periods = $this->seedPeriods($admin);

            foreach ($fixture['units'] as $unitDefinition) {
                $unit = Unit::where('code', $unitDefinition['unit_code'])
                    ->where('active', true)
                    ->firstOrFail();
                $responsible = User::where('unit_id', $unit->id)
                    ->where('role', 'UNIT')
                    ->where('active', true)
                    ->firstOrFail();
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
        });
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
                    'status' => 'DRAFT',
                ],
            );

            $period->update([
                'cycle_id' => $cycle->id,
                'deadline' => $deadline,
            ]);
            $periods[] = $period;
        }

        return $periods;
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
