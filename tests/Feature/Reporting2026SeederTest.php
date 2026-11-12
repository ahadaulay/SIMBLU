<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\ReportingPeriod;
use App\Services\IndicatorCalculator;
use Database\Seeders\AdminSeeder;
use Database\Seeders\Reporting2026Seeder;
use Database\Seeders\UnitAccountSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Reporting2026SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_quarterly_assignments_without_report_values(): void
    {
        $this->seedReporting2026();

        $this->assertDatabaseHas('reporting_cycles', [
            'year' => 2026,
            'frequency' => 'QUARTERLY',
        ]);
        $this->assertSame([1, 2, 3, 4], ReportingPeriod::query()
            ->where('year', 2026)
            ->orderBy('quarter')
            ->pluck('quarter')
            ->all());
        $this->assertDatabaseCount('indicator_categories', 18);
        $this->assertDatabaseCount('indicators', 66);
        $this->assertDatabaseCount('indicator_assignments', 264);
        $this->assertDatabaseCount('unit_reports', 0);
        $this->assertDatabaseCount('report_entries', 0);
        $this->assertSame(33, Indicator::where('form_type', 'FIXED')->count());
        $this->assertSame(33, Indicator::where('form_type', 'DYNAMIC')->count());

        $facultyDetail = Indicator::where('code', 'KL26-FTIK-01')->firstOrFail();
        $this->assertSame('DYNAMIC', $facultyDetail->form_type);
        $this->assertSame('DISTINCT', $facultyDetail->aggregation);
        $this->assertStringContainsString('Pendidikan Agama Islam', $facultyDetail->fields[0]['options']);

        $facultyRecap = Indicator::where('code', 'KL26-FTIK-02')->firstOrFail();
        $this->assertSame('FIXED', $facultyRecap->form_type);
        $this->assertSame(
            ['Lokal', 'Internasional', 'Nasional', 'Provinsi/Regional'],
            array_column($facultyRecap->items, 'label'),
        );

        $studentCount = Indicator::where('code', 'KL26-AKADEMIK-03')->firstOrFail();
        $this->assertSame([
            'Tarbiyah dan Ilmu Keguruan',
            'Syariah dan Ilmu Hukum',
            'Dakwah dan Ilmu Komunikasi',
            'Ekonomi dan Bisnis Islam',
            'Pascasarjana',
            'Fakultas Saintek',
        ], array_column($studentCount->items, 'label'));

        $calculator = app(IndicatorCalculator::class);
        Indicator::query()->each(fn (Indicator $indicator) => $calculator->validateDefinition(
            $indicator->fields,
            $indicator->items,
            $indicator->form_type,
            $indicator->result_field,
            $indicator->aggregation,
            $indicator->denominator_field,
        ));
    }

    public function test_it_is_idempotent_and_preserves_period_status(): void
    {
        $this->seedReporting2026();
        ReportingPeriod::where('year', 2026)
            ->where('quarter', 1)
            ->update(['status' => 'OPEN']);

        $this->seed(Reporting2026Seeder::class);

        $this->assertDatabaseCount('indicators', 66);
        $this->assertDatabaseCount('indicator_assignments', 264);
        $this->assertDatabaseHas('reporting_periods', [
            'year' => 2026,
            'quarter' => 1,
            'status' => 'OPEN',
        ]);
        $this->assertDatabaseCount('unit_reports', 0);
        $this->assertDatabaseCount('report_entries', 0);
    }

    private function seedReporting2026(): void
    {
        config()->set('reporting.seed_admin.email', 'superadmin@example.test');
        config()->set('reporting.seed_admin.password', 'AdminPassword123');
        config()->set('reporting.seed_unit.password', 'UnitPassword123');

        $this->seed([
            AdminSeeder::class,
            UnitSeeder::class,
            UnitAccountSeeder::class,
            Reporting2026Seeder::class,
        ]);
    }
}
