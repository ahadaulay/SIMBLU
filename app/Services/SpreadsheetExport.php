<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use ZipArchive;

class SpreadsheetExport
{
    private const CONTENT_TYPES_XML = <<<'XML'
        <?xml version="1.0"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
            <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
            <Default Extension="xml" ContentType="application/xml"/>
            <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
            <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
            <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
            <Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
        </Types>
        XML;

    private const PACKAGE_RELATIONSHIPS_XML = <<<'XML'
        <?xml version="1.0"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
        </Relationships>
        XML;

    private const WORKBOOK_XML = <<<'XML'
        <?xml version="1.0"?>
        <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
            <sheets>
                <sheet name="Rekap" sheetId="1" r:id="rId1"/>
                <sheet name="Rincian" sheetId="2" r:id="rId2"/>
                <sheet name="Belum terverifikasi" sheetId="3" r:id="rId3"/>
            </sheets>
        </workbook>
        XML;

    public function create(array $result): string
    {
        $isTemporary = $result['missing'] || $result['incompatible'];

        $summary = [
            ['Laporan Kinerja SIBLU'],
            ['Periode', implode(', ', $result['periods'])],
            ['Dibuat', $result['generated_at']],
            [
                'Status',
                $isTemporary
                    ? 'Sementara: data belum lengkap / versi berbeda'
                    : 'Data terverifikasi',
            ],
            [
                'Unit',
                'Kode',
                'Indikator',
                'Versi',
                'Periode',
                'Metode',
                'Hasil',
                'Triwulan terverifikasi',
                'Tahun sebelumnya',
                'Kondisi',
                'Perubahan (%)',
            ],
        ];

        $details = [[
            'Unit',
            'Indikator',
            'Versi',
            'Periode',
            'Uraian',
            'Field',
            'Satuan',
            'Nilai',
            'Penjelasan',
            'Laporan sumber',
        ]];

        foreach ($result['rows'] as $row) {
            $summary[] = [
                $row['unit'],
                $row['code'],
                $row['indicator'],
                $row['version'],
                $row['group'],
                config('reporting.aggregations')[$row['definition']['aggregation']],
                $row['value'],
                $row['period_count'],
                $row['previous_value'],
                $row['condition'],
                $row['change_percent'],
            ];

            foreach ($row['sources'] as $source) {
                foreach ($source['rows'] as $detail) {
                    foreach ($row['definition']['fields'] as $field) {
                        $details[] = [
                            $source['unit'],
                            $row['indicator'],
                            $row['version'],
                            $source['period'],
                            $detail['label'],
                            $field['label'],
                            $field['unit'],
                            $detail['values'][$field['code']] ?? null,
                            $source['explanation'],
                            $source['id'],
                        ];
                    }
                }

                if ($source['no_activity']) {
                    $details[] = [
                        $row['unit'],
                        $row['indicator'],
                        $row['version'],
                        $source['period'],
                        'Tidak ada kegiatan',
                        '',
                        '',
                        '',
                        $source['explanation'],
                        $source['id'],
                    ];
                }
            }
        }

        $missing = [['Unit', 'Periode', 'Indikator belum terverifikasi']];

        foreach ($result['missing'] as $row) {
            $missing[] = [$row['unit'], $row['period'], $row['indicator']];
        }

        foreach ($result['incompatible'] as $message) {
            $missing[] = [$message, '', 'Versi berbeda dengan pemetaan'];
        }

        $directory = storage_path('app/reporting-exports');
        File::ensureDirectoryExists($directory);

        $path = tempnam($directory, 'recap-');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Tidak dapat membuat file Excel.');
        }

        try {
            // File XLSX adalah arsip ZIP yang berisi dokumen XML dengan struktur baku.
            $zip->addFromString('[Content_Types].xml', self::CONTENT_TYPES_XML);
            $zip->addFromString('_rels/.rels', self::PACKAGE_RELATIONSHIPS_XML);
            $zip->addFromString('xl/workbook.xml', self::WORKBOOK_XML);

            $relations = <<<'XML'
                <?xml version="1.0"?>
                <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                XML;

            foreach ([$summary, $details, $missing] as $index => $rows) {
                $number = $index + 1;

                $relations .= sprintf(
                    '<Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet%d.xml"/>',
                    $number,
                    $number,
                );

                $zip->addFromString('xl/worksheets/sheet'.$number.'.xml', $this->sheet($rows));
            }

            $zip->addFromString('xl/_rels/workbook.xml.rels', $relations.'</Relationships>');

            if (! $zip->close()) {
                throw new \RuntimeException('Gagal menyelesaikan file Excel.');
            }
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }

        return $path;
    }

    private function sheet(array $rows): string
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <sheetViews><sheetView workbookViewId="0"/></sheetViews>
                <cols><col min="1" max="10" width="28" customWidth="1"/></cols>
                <sheetData>
            XML;

        foreach ($rows as $index => $row) {
            $xml .= '<row r="'.($index + 1).'">';

            foreach ($row as $column => $value) {
                $reference = chr(65 + $column).($index + 1);

                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$reference.'"><v>'.$value.'</v></c>';
                } else {
                    // Inline strings prevent formula injection from user-controlled text.
                    $cleanValue = preg_replace(
                        '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u',
                        '',
                        (string) $value,
                    );
                    $text = htmlspecialchars(
                        $cleanValue,
                        ENT_XML1 | ENT_QUOTES,
                        'UTF-8',
                    );

                    $xml .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
                }
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }
}
