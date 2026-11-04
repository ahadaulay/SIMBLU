<?php

namespace Tests\Unit;

use App\Services\ReferenceSpreadsheetExport;
use Tests\TestCase;
use ZipArchive;

class ReferenceSpreadsheetExportTest extends TestCase
{
    public function test_it_creates_reference_recap_and_unit_sheets(): void
    {
        $path = app(ReferenceSpreadsheetExport::class)->create([
            'periods' => ['TW 1 / 2026'],
            'units' => ['Layanan Akademik'],
            'rows' => [[
                'unit' => 'Layanan Akademik',
                'code' => 'IND002',
                'indicator' => 'Jumlah Total Mahasiswa Aktif S1',
                'category' => 'Mahasiswa',
                'group' => 'TW 1 / 2026',
                'previous_value' => 7450,
                'value' => 6574,
                'condition' => 'TURUN',
                'sources' => [[
                    'explanation' => 'Jumlah lulusan meningkat.',
                    'responsible_name' => 'Operator Layanan Akademik',
                ]],
            ]],
        ]);

        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($path) === true);
            $workbook = $zip->getFromName('xl/workbook.xml');
            $recap = $zip->getFromName('xl/worksheets/sheet1.xml');

            $this->assertStringContainsString('REKAP DATA', $workbook);
            $this->assertStringContainsString('Layanan Akademik', $workbook);
            $this->assertStringContainsString('Nama ASN Penanggung Jawab', $recap);
            $this->assertStringContainsString('Jumlah Total Mahasiswa Aktif S1', $recap);
        } finally {
            $zip->close();

            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_dynamic_indicator_detail_rows_appear_in_unit_sheet(): void
    {
        $path = app(ReferenceSpreadsheetExport::class)->create([
            'periods' => ['TW 1 / 2026'],
            'units' => ['Layanan Akademik'],
            'rows' => [[
                'unit' => 'Layanan Akademik',
                'code' => 'IND010',
                'indicator' => 'Mahasiswa Luar Negeri',
                'category' => 'Mahasiswa',
                'group' => 'TW 1 / 2026',
                'previous_value' => null,
                'value' => 200,
                'condition' => 'Tidak dapat dibandingkan',
                'definition' => ['form_type' => 'DYNAMIC', 'result_field' => 'jumlah'],
                'sources' => [[
                    'explanation' => '',
                    'responsible_name' => 'Operator Layanan Akademik',
                    'period' => 'TW 1 / 2026',
                    'rows' => [
                        ['key' => 'r1', 'label' => 'Kamboja', 'values' => ['jumlah' => 100]],
                        ['key' => 'r2', 'label' => 'Thailand', 'values' => ['jumlah' => 100]],
                    ],
                ]],
            ]],
        ]);

        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($path) === true);
            $unitSheet = $zip->getFromName('xl/worksheets/sheet2.xml');

            $this->assertStringContainsString('Mahasiswa Luar Negeri', $unitSheet);
            $this->assertStringContainsString('Kamboja', $unitSheet);
            $this->assertStringContainsString('Thailand', $unitSheet);
        } finally {
            $zip->close();

            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_fixed_indicator_detail_rows_appear_in_unit_sheet(): void
    {
        $path = app(ReferenceSpreadsheetExport::class)->create([
            'periods' => ['TW 1 / 2026'],
            'units' => ['Layanan Akademik'],
            'rows' => [[
                'unit' => 'Layanan Akademik',
                'code' => 'IND011',
                'indicator' => 'Sarana Prasarana',
                'category' => 'Fasilitas',
                'group' => 'TW 1 / 2026',
                'previous_value' => null,
                'value' => 15,
                'condition' => 'Tidak dapat dibandingkan',
                'definition' => ['form_type' => 'FIXED', 'result_field' => 'jumlah'],
                'sources' => [[
                    'explanation' => '',
                    'responsible_name' => 'Operator Layanan Akademik',
                    'period' => 'TW 1 / 2026',
                    'rows' => [
                        ['key' => 'ruang', 'label' => 'Ruang Kelas', 'values' => ['jumlah' => 10]],
                        ['key' => 'lab', 'label' => 'Laboratorium', 'values' => ['jumlah' => 5]],
                    ],
                ]],
            ]],
        ]);

        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($path) === true);
            $unitSheet = $zip->getFromName('xl/worksheets/sheet2.xml');

            $this->assertStringContainsString('Sarana Prasarana', $unitSheet);
            $this->assertStringContainsString('Ruang Kelas', $unitSheet);
            $this->assertStringContainsString('Laboratorium', $unitSheet);
        } finally {
            $zip->close();

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
