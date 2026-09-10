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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportingExampleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::where('email', config('reporting.seed_admin.email'))
                ->firstOrFail();

            $categories = $this->seedCategories();
            $periods = $this->seedPeriods($admin);

            foreach ($this->examples() as $example) {
                $unit = Unit::where('code', $example['unit'])->firstOrFail();
                $indicator = $this->seedIndicator($admin, $categories, $example);

                $this->seedAssignments($admin, $periods['current'], $unit, $indicator);
                $this->seedAssignments($admin, $periods['previous'], $unit, $indicator);
                $this->seedFirstQuarterRealization(
                    $admin,
                    $periods['current'][1],
                    $unit,
                    $indicator,
                    $example,
                    $example['value'],
                );
                $this->seedFirstQuarterRealization(
                    $admin,
                    $periods['previous'][1],
                    $unit,
                    $indicator,
                    $example,
                    $this->previousValue($example['code'], $example['value']),
                );
            }
        });
    }

    private function seedCategories(): array
    {
        $categories = [
            'MAHASISWA' => 'Mahasiswa',
            'LULUSAN' => 'Lulusan',
            'PENELITIAN' => 'Publikasi Penelitian',
            'SDM' => 'Sumber Daya Manusia',
            'KERJASAMA' => 'Kerja Sama',
            'PROGRAM-STUDI' => 'Program Studi',
        ];

        $records = [];

        foreach ($categories as $code => $name) {
            $records[$code] = IndicatorCategory::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'active' => true],
            );
        }

        return $records;
    }

    private function seedPeriods(User $admin): array
    {
        $periods = [];

        foreach ([2025 => 'previous', 2026 => 'current'] as $year => $key) {
            $cycle = ReportingCycle::updateOrCreate(
                ['year' => $year],
                [
                    'frequency' => 'QUARTERLY',
                    'created_by' => $admin->id,
                ],
            );

            foreach (range(1, 4) as $quarter) {
                $periods[$key][$quarter] = ReportingPeriod::updateOrCreate(
                    ['year' => $year, 'quarter' => $quarter],
                    [
                        'cycle_id' => $cycle->id,
                        'deadline' => sprintf('%d-%02d-%02d', $year, $quarter * 3, [3 => 31, 6 => 30, 9 => 30, 12 => 31][$quarter * 3]),
                        'status' => $year === 2025 || $quarter === 1 ? 'CLOSED' : ($quarter === 3 ? 'OPEN' : 'DRAFT'),
                    ],
                );
            }
        }

        return $periods;
    }

    private function seedIndicator(
        User $admin,
        array $categories,
        array $example,
    ): Indicator {
        return Indicator::updateOrCreate(
            ['code' => $example['code']],
            [
                'category_id' => $categories[$example['category']]->id,
                'name' => $example['name'],
                'description' => 'Contoh indikator dari rekap DATA KINERJA LAYANAN UNIT.',
                'form_type' => 'SINGLE',
                'aggregation' => $example['aggregation'],
                'direction' => 'NEUTRAL',
                'result_field' => 'value',
                'denominator_field' => null,
                'ratio_scale' => 1,
                'requires_attachment' => false,
                'requires_explanation' => false,
                'active' => true,
                'version' => 1,
                'fields' => [$this->numberField($example['unit_label'])],
                'items' => [],
                'created_by' => $admin->id,
            ],
        );
    }

    private function seedAssignments(
        User $admin,
        array $periods,
        Unit $unit,
        Indicator $indicator,
    ): void {
        $responsible = User::where('unit_id', $unit->id)
            ->where('role', 'UNIT')
            ->where('active', true)
            ->firstOrFail();

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

    private function seedFirstQuarterRealization(
        User $admin,
        ReportingPeriod $period,
        Unit $unit,
        Indicator $indicator,
        array $example,
        int $value,
    ): void {
        $report = UnitReport::updateOrCreate(
            [
                'unit_id' => $unit->id,
                'period_id' => $period->id,
            ],
            [
                'created_by' => $admin->id,
                'verified_by' => $admin->id,
                'status' => 'VERIFIED',
                'version' => 1,
                'submitted_at' => $period->year.'-03-31 09:00:00',
                'verified_at' => $period->year.'-03-31 10:00:00',
            ],
        );

        $report->entries()->updateOrCreate(
            ['indicator_id' => $indicator->id],
            [
                // Salinan definisi menjaga laporan lama tetap konsisten saat master berubah.
                'definition' => $indicator->load('category')->definition(),
                'required' => true,
                'no_activity' => false,
                'rows' => [[
                    'key' => 'single',
                    'label' => $indicator->name,
                    'values' => ['value' => $value],
                ]],
                'explanation' => $example['explanation'],
                'responsible_name' => User::where('unit_id', $unit->id)
                    ->where('role', 'UNIT')
                    ->value('name'),
            ],
        );
    }

    private function numberField(string $unit): array
    {
        return [
            'code' => 'value',
            'label' => 'Jumlah',
            'type' => 'NUMBER',
            'unit' => $unit,
            'required' => true,
            'decimals' => 0,
            'operation' => null,
            'operands' => [],
            'options' => [],
            'min' => 0,
            'max' => null,
        ];
    }

    private function previousValue(string $code, int $currentValue): int
    {
        return match ($code) {
            'IND002' => 7450,
            'IND003' => 541,
            'IND004' => 37,
            'IND008' => 1174,
            'IND013' => 847,
            'IND019' => 1,
            'IND020' => 17,
            'IND021' => 35,
            'IND022' => 234,
            'IND031' => 175,
            default => $currentValue,
        };
    }

    private function examples(): array
    {
        return [
            $this->example(
                'IND001',
                'MAHASISWA',
                'Jumlah Total Mahasiswa Aktif Diploma (Profesi)',
                'FTIK',
                'LAST',
                0,
                'orang',
                'Tidak ada program diploma.',
            ),
            $this->example(
                'IND002',
                'MAHASISWA',
                'Jumlah Total Mahasiswa Aktif S1',
                'AKADEMIK',
                'LAST',
                6574,
                'orang',
                'Banyak mahasiswa yang lulus dan target mahasiswa baru tidak tercapai tahun 2025.',
            ),
            $this->example(
                'IND003',
                'MAHASISWA',
                'Jumlah Total Mahasiswa Aktif S2',
                'PASCA',
                'LAST',
                549,
                'orang',
                'Ada peningkatan jumlah pendaftar Program Magister.',
            ),
            $this->example(
                'IND004',
                'MAHASISWA',
                'Jumlah Total Mahasiswa Aktif S3',
                'PASCA',
                'LAST',
                45,
                'orang',
                'Penambahan Prodi PAI pada Program Doktor.',
            ),
            $this->example(
                'IND005',
                'MAHASISWA',
                'Jumlah Daya Tampung',
                'BAG-UMUM',
                'LAST',
                2000,
                'orang',
            ),
            $this->example(
                'IND008',
                'MAHASISWA',
                'Jumlah Mahasiswa Penerima Beasiswa',
                'AKADEMIK',
                'LAST',
                939,
                'orang',
                'Penurunan kuota beasiswa.',
            ),
            $this->example(
                'IND013',
                'LULUSAN',
                'Jumlah Lulusan S1',
                'AKADEMIK',
                'SUM',
                928,
                'orang',
                'Pelaksanaan dua gelombang wisuda pada bulan Januari.',
            ),
            $this->example(
                'IND019',
                'PENELITIAN',
                'Jumlah Keluaran Penelitian dengan Rekognisi Internasional',
                'LPPM',
                'SUM',
                2,
                'keluaran',
            ),
            $this->example(
                'IND020',
                'PENELITIAN',
                'Jumlah Keluaran Penelitian dengan Rekognisi Nasional',
                'LPPM',
                'SUM',
                38,
                'keluaran',
            ),
            $this->example(
                'IND021',
                'PENELITIAN',
                'Jumlah Keluaran Penelitian yang Digunakan Pemangku Kepentingan',
                'LPPM',
                'SUM',
                88,
                'keluaran',
            ),
            $this->example(
                'IND022',
                'SDM',
                'Jumlah Dosen dengan NIDN',
                'SDM',
                'LAST',
                236,
                'orang',
            ),
            $this->example(
                'IND031',
                'SDM',
                'Jumlah Tenaga Kependidikan',
                'SDM',
                'LAST',
                179,
                'orang',
                'Penambahan Penata Layanan Operasional.',
            ),
            $this->example(
                'IND040',
                'PROGRAM-STUDI',
                'Jumlah Program Studi Terakreditasi A atau Unggul',
                'LPM',
                'LAST',
                8,
                'program studi',
            ),
        ];
    }

    private function example(
        string $code,
        string $category,
        string $name,
        string $unit,
        string $aggregation,
        int $value,
        string $unitLabel,
        ?string $explanation = null,
    ): array {
        return [
            'code' => $code,
            'category' => $category,
            'name' => $name,
            'unit' => $unit,
            'aggregation' => $aggregation,
            'value' => $value,
            'unit_label' => $unitLabel,
            'explanation' => $explanation,
        ];
    }
}
