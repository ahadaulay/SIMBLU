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
}
