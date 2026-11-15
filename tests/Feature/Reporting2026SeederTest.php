<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\ReportingPeriod;
use App\Models\UnitReport;
use App\Models\User;
use App\Services\IndicatorCalculator;
use App\Services\ReferenceSpreadsheetExport;
use App\Services\ReportRecap;
use Database\Seeders\AdminSeeder;
use Database\Seeders\Reporting2026Seeder;
use Database\Seeders\UnitAccountSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class Reporting2026SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_source_values_as_verified_reports_and_prepares_empty_quarter_four(): void
    {
        $this->seedReporting2026();

        $this->assertDatabaseHas('reporting_cycles', [
            'year' => 2026,
            'frequency' => 'QUARTERLY',
        ]);
        $periods = ReportingPeriod::query()
            ->where('year', 2026)
            ->orderBy('quarter')
            ->get();
        $this->assertSame([1, 2, 3, 4], $periods->pluck('quarter')->all());
        $this->assertSame(['CLOSED', 'CLOSED', 'CLOSED', 'OPEN'], $periods->pluck('status')->all());
        $this->assertDatabaseCount('indicator_categories', 18);
        $this->assertDatabaseCount('indicators', 66);
        $this->assertDatabaseCount('indicator_assignments', 264);
        $this->assertDatabaseCount('unit_reports', 72);
        $this->assertDatabaseCount('report_entries', 264);
        $this->assertSame(54, UnitReport::where('status', 'VERIFIED')->count());
        $this->assertSame(18, UnitReport::where('status', 'DRAFT')->count());
        $this->assertSame(38, Indicator::where('form_type', 'FIXED')->count());
        $this->assertSame(28, Indicator::where('form_type', 'DYNAMIC')->count());

        $facultyDetails = Indicator::where('name', 'REALISASI DATA JUMLAH PRESTASI MAHASISWA PERPRODI TAHUN 2026')->get();
        $this->assertCount(5, $facultyDetails);
        $facultyDetails->each(function (Indicator $indicator): void {
            $this->assertSame('FIXED', $indicator->form_type);
            $this->assertSame('SUM', $indicator->aggregation);
            $this->assertSame('jumlah', $indicator->result_field);
            $this->assertSame(
                ['lokal', 'regional', 'nasional', 'internasional', 'jumlah'],
                array_column($indicator->fields, 'code'),
            );
            $this->assertSame(
                ['NUMBER', 'NUMBER', 'NUMBER', 'NUMBER', 'CALCULATED'],
                array_column($indicator->fields, 'type'),
            );
            $this->assertSame('lokal,regional,nasional,internasional', $indicator->fields[4]['operands']);
            $this->assertNotEmpty($indicator->items);
        });

        $facultyDetail = $facultyDetails->firstWhere('code', 'KL26-FTIK-01');
        $this->assertSame([
            'Pendidikan Agama Islam',
            'Pendidikan Bahasa Arab',
            'Tadris Bahasa Inggris',
            'Pendidikan Matematika',
            'Pend. Guru Madrasah Ibtidaiyah (PGMI)',
            'Pendidikan Islam Anak Usia Dini',
            'Tadris Kimia',
            'Tadris Biologi',
            'Tadris Fisika',
            'Tadris Bahasa Indonesia',
            'Teknologi Informasi',
        ], array_column($facultyDetail->items, 'label'));

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

        $academicQuarterThree = UnitReport::with(['unit', 'period', 'entries'])
            ->whereHas('unit', fn ($query) => $query->where('code', 'AKADEMIK'))
            ->whereHas('period', fn ($query) => $query->where('year', 2026)->where('quarter', 3))
            ->firstOrFail();
        $studentEntry = $academicQuarterThree->entries->firstWhere('indicator_id', $studentCount->id);
        $this->assertEquals(4164, $studentEntry->rows[0]['values']['jumlah']);

        $facultyQuarterTwo = UnitReport::with(['unit', 'period', 'entries'])
            ->whereHas('unit', fn ($query) => $query->where('code', 'FTIK'))
            ->whereHas('period', fn ($query) => $query->where('year', 2026)->where('quarter', 2))
            ->firstOrFail();
        $achievementEntry = $facultyQuarterTwo->entries->firstWhere('indicator_id', $facultyDetail->id);
        $this->assertEquals(9, $achievementEntry->rows[0]['values']['jumlah']);
        $this->assertCount(9, $achievementEntry->rows[0]['details']);
        $this->assertStringStartsWith(
            'Yolanda Anggra Lexa',
            $achievementEntry->rows[0]['details'][0]['name'],
        );

        $accreditation = Indicator::where('code', 'KL26-LPM-01')->firstOrFail();
        $this->assertSame('DETAIL', $accreditation->aggregation);
        $this->assertSame('TEXT', $accreditation->fields[0]['type']);
        $lpmQuarterOne = UnitReport::with(['unit', 'period', 'entries'])
            ->whereHas('unit', fn ($query) => $query->where('code', 'LPM'))
            ->whereHas('period', fn ($query) => $query->where('year', 2026)->where('quarter', 1))
            ->firstOrFail();
        $accreditationEntry = $lpmQuarterOne->entries->firstWhere('indicator_id', $accreditation->id);
        $this->assertSame('Unggul', $accreditationEntry->rows[0]['values']['jumlah']);

        $quarterFourReports = UnitReport::with(['period', 'entries'])
            ->whereHas('period', fn ($query) => $query->where('year', 2026)->where('quarter', 4))
            ->get();
        $this->assertCount(18, $quarterFourReports);
        $quarterFourReports->each(function (UnitReport $report): void {
            $this->assertSame('DRAFT', $report->status);
            $this->assertNull($report->submitted_at);
            $this->assertNull($report->verified_at);
            $report->entries->each(function ($entry): void {
                foreach ($entry->rows as $row) {
                    $this->assertNotEmpty($row['label']);
                    $this->assertSame([], array_filter(
                        $row['values'],
                        fn ($value) => $value !== null && $value !== '',
                    ));
                    $this->assertSame([], $row['details']);
                }
            });
        });

        $calculator = app(IndicatorCalculator::class);
        $this->assertNull($calculator->fieldValue($facultyDetail->fields[4], [
            'lokal' => '',
            'regional' => 2,
            'nasional' => 3,
            'internasional' => 4,
        ]));
        $this->assertSame(10.0, $calculator->fieldValue($facultyDetail->fields[4], [
            'lokal' => 1,
            'regional' => 2,
            'nasional' => 3,
            'internasional' => 4,
        ]));
        Indicator::query()->each(fn (Indicator $indicator) => $calculator->validateDefinition(
            $indicator->fields,
            $indicator->items,
            $indicator->form_type,
            $indicator->result_field,
            $indicator->aggregation,
            $indicator->denominator_field,
        ));

        $this->actingAs(User::where('email', 'superadmin@example.test')->firstOrFail());
        $result = app(ReportRecap::class)->build([
            'periods' => $periods->pluck('id')->all(),
            'units' => [],
            'indicators' => [],
            'grouping' => 'PERIOD',
        ]);
        $path = app(ReferenceSpreadsheetExport::class)->create($result);
        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($path) === true);
            $recap = $zip->getFromName('xl/worksheets/sheet1.xml');
            $unitSheets = '';

            foreach (range(2, 19) as $sheet) {
                $unitSheets .= $zip->getFromName('xl/worksheets/sheet'.$sheet.'.xml');
            }

            foreach (range(1, 4) as $quarter) {
                $this->assertStringContainsString('PERBANDINGAN TW '.$quarter, $recap);
            }
            $this->assertStringContainsString('Yolanda Anggra Lexa', $unitSheets);
            $this->assertStringContainsString('Unggul', $unitSheets);
            $this->assertStringContainsString('<v>4164</v>', $unitSheets);
        } finally {
            $zip->close();

            if (is_file($path)) {
                unlink($path);
            }
        }
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
        $this->assertDatabaseCount('unit_reports', 72);
        $this->assertDatabaseCount('report_entries', 264);
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
