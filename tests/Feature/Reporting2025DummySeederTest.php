<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\ReportEntry;
use App\Models\ReportingPeriod;
use App\Services\IndicatorCalculator;
use Database\Seeders\AdminSeeder;
use Database\Seeders\Reporting2025DummySeeder;
use Database\Seeders\Reporting2026Seeder;
use Database\Seeders\UnitAccountSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Reporting2025DummySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_complete_2025_dummy_reports_with_matching_details(): void
    {
        $this->seedReportingData();

        $this->assertSame([1, 2, 3, 4], ReportingPeriod::query()
            ->where('year', 2025)
            ->orderBy('quarter')
            ->pluck('quarter')
            ->all());
        $this->assertSame(264, DB::table('indicator_assignments')
            ->join('reporting_periods', 'reporting_periods.id', '=', 'indicator_assignments.period_id')
            ->where('reporting_periods.year', 2025)
            ->count());
        $this->assertSame(72, DB::table('unit_reports')
            ->join('reporting_periods', 'reporting_periods.id', '=', 'unit_reports.period_id')
            ->where('reporting_periods.year', 2025)
            ->where('unit_reports.status', 'VERIFIED')
            ->count());
        $this->assertDatabaseCount('report_entries', 264);

        $calculator = app(IndicatorCalculator::class);
        ReportEntry::query()->each(function (ReportEntry $entry) use ($calculator): void {
            $normalized = $calculator->normalize($entry->definition, $entry->rows, true);
            $this->assertNotEmpty($normalized);

            foreach ($normalized as $row) {
                $this->assertNotEmpty($row['details']);
            }
        });

        $achievement = ReportEntry::query()
            ->whereHas('report.period', fn ($query) => $query->where('year', 2025)->where('quarter', 1))
            ->where('indicator_id', Indicator::where('code', 'KL26-FTIK-01')->value('id'))
            ->firstOrFail();
        $firstRow = $achievement->rows[0];

        $this->assertStringContainsString('2025', $achievement->definition['name']);
        $this->assertStringNotContainsString('2026', $achievement->definition['name']);
        $this->assertSame(['lokal', 'regional', 'nasional', 'internasional', 'jumlah'], array_keys($firstRow['values']));
        $this->assertEquals(
            $firstRow['values']['lokal'] + $firstRow['values']['regional'] + $firstRow['values']['nasional'] + $firstRow['values']['internasional'],
            $firstRow['values']['jumlah'],
        );
        $this->assertCount(4, $firstRow['details']);
        $this->assertEquals($firstRow['values']['jumlah'], array_sum(array_column($firstRow['details'], 'amount')));

        $this->seed(Reporting2025DummySeeder::class);

        $this->assertDatabaseCount('unit_reports', 72);
        $this->assertDatabaseCount('report_entries', 264);
    }

    private function seedReportingData(): void
    {
        config()->set('reporting.seed_admin.email', 'superadmin@example.test');
        config()->set('reporting.seed_admin.password', 'AdminPassword123');
        config()->set('reporting.seed_unit.password', 'UnitPassword123');

        $this->seed([
            AdminSeeder::class,
            UnitSeeder::class,
            UnitAccountSeeder::class,
            Reporting2026Seeder::class,
            Reporting2025DummySeeder::class,
        ]);
    }
}
