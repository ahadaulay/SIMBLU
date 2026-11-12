<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\IndicatorAssignment;
use App\Models\IndicatorCategory;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use App\Models\UnitReport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompleteWorkbookSeeder extends Seeder
{
    private const CODE = 'DATA-KINERJA-2025-2026';

    private const SHA256 = '9d7987c49f5d911ea632cd4955cf49ce97c1679837bbeb0fd1b844e64b64944a';

    private const SHEET_COUNT = 19;

    private const COORDINATE_COUNT = 17542;

    private const NONEMPTY_COUNT = 3753;

    private const FORMULA_COUNT = 4;

    private const MERGED_COUNT = 820;

    public function run(): void
    {
        $fixture = $this->loadFixture();
        $this->validateFixture($fixture);

        DB::transaction(function () use ($fixture): void {
            $templateId = $this->seedWorkbookCoordinates($fixture);
            $this->assertStoredCoordinates($templateId);
        });
    }

    private function loadFixture(): array
    {
        $path = database_path('seeders/data/data-kinerja-layanan-unit-2025-2026.json.gz');
        $compressed = file_get_contents($path);

        if ($compressed === false) {
            throw new RuntimeException('Fixture workbook lengkap tidak ditemukan.');
        }

        $json = gzdecode($compressed);

        if ($json === false) {
            throw new RuntimeException('Fixture workbook lengkap tidak dapat dibaca.');
        }

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    private function validateFixture(array $fixture): void
    {
        $sourcePath = database_path('seeders/data/data-kinerja-layanan-unit-2025-2026.xlsx');
        $expected = [
            'code' => self::CODE,
            'sha256' => self::SHA256,
            'sheet_count' => self::SHEET_COUNT,
            'coordinate_count' => self::COORDINATE_COUNT,
            'nonempty_count' => self::NONEMPTY_COUNT,
            'formula_count' => self::FORMULA_COUNT,
            'merged_count' => self::MERGED_COUNT,
        ];

        foreach ($expected as $key => $value) {
            if (($fixture[$key] ?? null) !== $value) {
                throw new RuntimeException('Fixture workbook tidak lengkap pada pemeriksaan '.$key.'.');
            }
        }

        if (! is_file($sourcePath) || hash_file('sha256', $sourcePath) !== self::SHA256) {
            throw new RuntimeException('File Excel baku berubah atau tidak lengkap.');
        }
    }

    private function seedWorkbookCoordinates(array $fixture): int
    {
        DB::table('reporting_workbook_templates')->updateOrInsert(
            ['code' => self::CODE],
            [
                'name' => $fixture['name'],
                'year_start' => $fixture['year_start'],
                'year_end' => $fixture['year_end'],
                'source_filename' => $fixture['source_filename'],
                'source_path' => 'database/seeders/data/data-kinerja-layanan-unit-2025-2026.xlsx',
                'sha256' => $fixture['sha256'],
                'sheet_count' => $fixture['sheet_count'],
                'coordinate_count' => $fixture['coordinate_count'],
                'nonempty_count' => $fixture['nonempty_count'],
                'formula_count' => $fixture['formula_count'],
                'merged_count' => $fixture['merged_count'],
                'style_count' => $fixture['style_count'],
                'metadata' => json_encode([
                    'source_filename' => $fixture['source_filename'],
                    'years' => [$fixture['year_start'], $fixture['year_end']],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $templateId = (int) DB::table('reporting_workbook_templates')
            ->where('code', self::CODE)
            ->value('id');

        foreach ($fixture['sheets'] as $sheet) {
            $sheetMetadata = $sheet;
            unset($sheetMetadata['cells'], $sheetMetadata['position'], $sheetMetadata['name'], $sheetMetadata['max_row'], $sheetMetadata['max_column']);

            DB::table('reporting_workbook_sheets')->updateOrInsert(
                [
                    'template_id' => $templateId,
                    'position' => $sheet['position'],
                ],
                [
                    'name' => $sheet['name'],
                    'max_row' => $sheet['max_row'],
                    'max_column' => $sheet['max_column'],
                    'metadata' => json_encode($sheetMetadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $sheetId = (int) DB::table('reporting_workbook_sheets')
                ->where('template_id', $templateId)
                ->where('position', $sheet['position'])
                ->value('id');

            foreach (array_chunk($sheet['cells'], 500) as $cells) {
                $records = array_map(fn (array $cell) => [
                    'sheet_id' => $sheetId,
                    'row_number' => $cell['row'],
                    'column_number' => $cell['column'],
                    'value_json' => json_encode(
                        $cell['value'],
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
                    ),
                    'data_type' => $cell['data_type'],
                    'style_id' => $cell['style_id'],
                    'number_format' => $cell['number_format'],
                    'metadata' => json_encode([
                        'hyperlink' => $cell['hyperlink'],
                        'comment' => $cell['comment'],
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ], $cells);

                DB::table('reporting_workbook_cells')->upsert(
                    $records,
                    ['sheet_id', 'row_number', 'column_number'],
                    ['value_json', 'data_type', 'style_id', 'number_format', 'metadata'],
                );
            }
        }

        return $templateId;
    }

    private function seedSummaryIndicators(array $fixture): void
    {
        $admin = User::where('email', config('reporting.seed_admin.email'))->firstOrFail();
        $periods = ReportingPeriod::whereIn('year', [2025, 2026])
            ->where('quarter', 1)
            ->get()
            ->keyBy('year');
        $summary = collect($fixture['sheets'])->firstWhere('name', 'REKAP DATA ');
        $matrix = [];

        foreach ($summary['cells'] as $cell) {
            $matrix[$cell['row']][$cell['column']] = $cell['value'];
        }

        $category = null;

        for ($row = 4; $row <= 52; $row++) {
            $number = $matrix[$row][1] ?? null;
            $name = $matrix[$row][2] ?? null;

            if (is_string($number) && $name === null) {
                $category = $this->seedCategory($number);

                continue;
            }

            if (! is_numeric($number) || ! is_string($name) || ! $category) {
                continue;
            }

            $unit = $this->unitFor((string) ($matrix[$row][7] ?? ''));
            $responsible = User::where('unit_id', $unit->id)
                ->where('role', 'UNIT')
                ->where('active', true)
                ->firstOrFail();
            $indicator = Indicator::updateOrCreate(
                ['code' => sprintf('IND%03d', $number)],
                [
                    'category_id' => $category->id,
                    'name' => $name,
                    'description' => 'Sumber: REKAP DATA baris '.$row.' pada workbook baku 2025-2026.',
                    'form_type' => 'SINGLE',
                    'aggregation' => 'LAST',
                    'direction' => 'NEUTRAL',
                    'result_field' => 'value',
                    'denominator_field' => null,
                    'ratio_scale' => 1,
                    'requires_attachment' => false,
                    'requires_explanation' => false,
                    'active' => true,
                    'version' => 1,
                    'fields' => [[
                        'code' => 'value',
                        'label' => 'Jumlah',
                        'type' => 'NUMBER',
                        'unit' => '',
                        'required' => true,
                        'decimals' => 0,
                        'operation' => null,
                        'operands' => [],
                        'options' => [],
                        'min' => null,
                        'max' => null,
                    ]],
                    'items' => [],
                    'created_by' => $admin->id,
                ],
            );

            foreach (ReportingPeriod::whereIn('year', [2025, 2026])->get() as $period) {
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

            $this->seedVerifiedValue(
                $admin,
                $unit,
                $indicator,
                $periods->get(2025),
                $matrix[$row][3] ?? null,
                $matrix[$row][6] ?? null,
                $matrix[$row][8] ?? null,
            );
            $this->seedVerifiedValue(
                $admin,
                $unit,
                $indicator,
                $periods->get(2026),
                $matrix[$row][4] ?? null,
                $matrix[$row][6] ?? null,
                $matrix[$row][8] ?? null,
            );
        }
    }

    private function seedCategory(string $sourceName): IndicatorCategory
    {
        $name = preg_replace('/^[A-Z]\.\s*/u', '', trim($sourceName));
        $code = match (true) {
            str_contains($name, 'Mahasiswa') => 'MAHASISWA',
            str_contains($name, 'Lulusan') => 'LULUSAN',
            str_contains($name, 'Penelitian') => 'PENELITIAN',
            str_contains($name, 'SDM') => 'SDM',
            str_contains($name, 'Kerjasama') => 'KERJASAMA',
            default => 'PROGRAM-STUDI',
        };

        return IndicatorCategory::updateOrCreate(
            ['code' => $code],
            ['name' => $name, 'active' => true],
        );
    }

    private function unitFor(string $label): Unit
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', str_replace('�', '', trim($label))));
        $code = match (true) {
            str_contains($normalized, 'ftik') => 'FTIK',
            str_contains($normalized, 'layanan akademik') => 'AKADEMIK',
            str_contains($normalized, 'pascasarjana') && str_contains($normalized, 'fakultas') => 'FAKULTAS-PASCA',
            str_contains($normalized, 'pascasarjana') => 'PASCA',
            str_contains($normalized, 'bagian umum') => 'BAG-UMUM',
            str_contains($normalized, 'upt') && str_contains($normalized, 'karir') => 'UPT-KARIR',
            str_contains($normalized, 'lppm') => 'LPPM',
            str_contains($normalized, 'sdm') => 'SDM',
            str_contains($normalized, 'lpm') => 'LPM',
            str_contains($normalized, 'fakultas') => 'FAKULTAS',
            default => throw new RuntimeException('Unit penanggung jawab tidak dikenali: '.$label),
        };

        return Unit::where('code', $code)->firstOrFail();
    }

    private function seedVerifiedValue(
        User $admin,
        Unit $unit,
        Indicator $indicator,
        ?ReportingPeriod $period,
        mixed $value,
        mixed $explanation,
        mixed $responsibleName,
    ): void {
        if (! $period) {
            throw new RuntimeException('Periode Triwulan I 2025 dan 2026 wajib tersedia.');
        }

        $report = UnitReport::updateOrCreate(
            ['unit_id' => $unit->id, 'period_id' => $period->id],
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
                'definition' => $indicator->load('category')->definition(),
                'required' => true,
                'no_activity' => false,
                'rows' => [[
                    'key' => 'single',
                    'label' => $indicator->name,
                    'values' => ['value' => $value],
                ]],
                'explanation' => $explanation,
                'responsible_name' => $responsibleName,
            ],
        );
    }

    private function assertStoredCoordinates(int $templateId): void
    {
        $sheetCount = DB::table('reporting_workbook_sheets')
            ->where('template_id', $templateId)
            ->count();
        $coordinateCount = DB::table('reporting_workbook_cells')
            ->join('reporting_workbook_sheets', 'reporting_workbook_sheets.id', '=', 'reporting_workbook_cells.sheet_id')
            ->where('reporting_workbook_sheets.template_id', $templateId)
            ->count();

        if ($sheetCount !== self::SHEET_COUNT || $coordinateCount !== self::COORDINATE_COUNT) {
            throw new RuntimeException('Seeder workbook tidak menyimpan seluruh sheet dan koordinat.');
        }
    }
}
