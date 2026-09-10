<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use ZipArchive;

class ReferenceSpreadsheetExport
{
    public function create(array $result): string
    {
        $sheets = $this->buildSheets($result);
        $directory = storage_path('app/reporting-exports');
        File::ensureDirectoryExists($directory);
        $path = tempnam($directory, 'laporan-baku-');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Tidak dapat membuat file Excel baku.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
            $zip->addFromString('_rels/.rels', $this->packageRelationships());
            $zip->addFromString('xl/workbook.xml', $this->workbook($sheets));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($sheets)));
            $zip->addFromString('xl/styles.xml', $this->styles());

            foreach ($sheets as $index => $sheet) {
                $zip->addFromString(
                    'xl/worksheets/sheet'.($index + 1).'.xml',
                    $this->sheet($sheet['rows'], $sheet['widths'], $sheet['merges']),
                );
            }

            if (! $zip->close()) {
                throw new \RuntimeException('Gagal menyelesaikan file Excel baku.');
            }
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }

        return $path;
    }

    private function buildSheets(array $result): array
    {
        $summaryRows = [[
            'values' => ['LAPORAN CAPAIAN KINERJA '.mb_strtoupper(implode(', ', $result['periods']))],
            'style' => 1,
        ], [
            'values' => [],
        ], [
            'values' => [
                'No',
                'Indikator',
                'Tahun Sebelumnya',
                'Tahun Berjalan',
                'Kondisi (SAMA/NAIK/TURUN)',
                'Penjelasan Penyebab Kondisi',
                'Penanggung Jawab',
                'Nama ASN Penanggung Jawab',
            ],
            'style' => 2,
        ]];

        $number = 1;
        $lastCategory = null;

        foreach ($result['rows'] as $row) {
            if ($lastCategory !== $row['category']) {
                $summaryRows[] = [
                    'values' => [$row['category']],
                    'style' => 3,
                ];
                $lastCategory = $row['category'];
            }

            $sources = collect($row['sources']);
            $summaryRows[] = [
                'values' => [
                    $number++,
                    $row['indicator'].' - '.$row['group'],
                    $row['previous_value'],
                    $row['value'],
                    $row['condition'],
                    $sources->pluck('explanation')->filter()->unique()->implode('; '),
                    $row['unit'],
                    $sources->pluck('responsible_name')->filter()->unique()->implode(', '),
                ],
                'style' => 4,
            ];
        }

        $sheets = [[
            'name' => 'REKAP DATA',
            'rows' => $summaryRows,
            'widths' => [7, 52, 18, 18, 22, 48, 28, 30],
            'merges' => ['A1:H1'],
        ]];

        $rowsByUnit = collect($result['rows'])->groupBy('unit');

        foreach ($result['units'] as $unit) {
            $unitRows = $rowsByUnit->get($unit, collect());
            $periods = collect($result['periods']);
            $headers = ['No', 'Uraian'];

            foreach ($periods as $period) {
                $headers[] = $period.' - Tahun Sebelumnya';
                $headers[] = $period.' - Tahun Berjalan';
            }

            $rows = [[
                'values' => ['REALISASI DATA '.mb_strtoupper($unit)],
                'style' => 1,
            ], [
                'values' => $headers,
                'style' => 2,
            ]];

            foreach ($unitRows->groupBy('indicator')->values() as $index => $indicatorRows) {
                $first = $indicatorRows->first();
                $values = [$index + 1, $first['indicator']];

                foreach ($periods as $period) {
                    $periodRow = $indicatorRows->firstWhere('group', $period);
                    $values[] = $periodRow['previous_value'] ?? null;
                    $values[] = $periodRow['value'] ?? null;
                }

                $rows[] = ['values' => $values, 'style' => 4];
            }

            $lastColumn = $this->columnName(count($headers));
            $sheets[] = [
                'name' => $this->uniqueSheetName($unit, array_column($sheets, 'name')),
                'rows' => $rows,
                'widths' => array_merge([7, 52], array_fill(0, max(0, count($headers) - 2), 19)),
                'merges' => ['A1:'.$lastColumn.'1'],
            ];
        }

        return $sheets;
    }

    private function sheet(array $rows, array $widths, array $merges): string
    {
        $columns = '';

        foreach ($widths as $index => $width) {
            $number = $index + 1;
            $columns .= '<col min="'.$number.'" max="'.$number.'" width="'.$width.'" customWidth="1"/>';
        }

        $data = '';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $style = $row['style'] ?? 0;
            $data .= '<row r="'.$excelRow.'">';

            foreach ($row['values'] as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).$excelRow;
                $data .= $this->cell($reference, $value, $style);
            }

            $data .= '</row>';
        }

        $mergeXml = $merges
            ? '<mergeCells count="'.count($merges).'">'.collect($merges)->map(fn ($merge) => '<mergeCell ref="'.$merge.'"/>')->implode('').'</mergeCells>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$columns.'</cols><sheetData>'.$data.'</sheetData>'.$mergeXml
            .'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    private function cell(string $reference, mixed $value, int $style): string
    {
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'" s="'.$style.'"><v>'.$value.'</v></c>';
        }

        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value);
        $text = htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
    }

    private function contentTypes(int $sheetCount): string
    {
        $overrides = '';

        for ($number = 1; $number <= $sheetCount; $number++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$number.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides.'</Types>';
    }

    private function workbook(array $sheets): string
    {
        $sheetXml = '';

        foreach ($sheets as $index => $sheet) {
            $name = htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $sheetXml .= '<sheet name="'.$name.'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetXml.'</sheets></workbook>';
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $relationships = '';

        for ($number = 1; $number <= $sheetCount; $number++) {
            $relationships .= '<Relationship Id="rId'.$number.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$number.'.xml"/>';
        }

        $relationships .= '<Relationship Id="rId'.($sheetCount + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    private function packageRelationships(): string
    {
        return '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="14"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/><color rgb="FFFFFFFF"/></font></fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9EAF7"/></patternFill></fill></fills>'
            .'<borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function uniqueSheetName(string $name, array $existing): string
    {
        $base = mb_substr(preg_replace('#[\\/?*\[\]:]#u', '-', trim($name)), 0, 31);
        $candidate = $base !== '' ? $base : 'Unit';
        $suffix = 2;

        while (in_array($candidate, $existing, true)) {
            $candidate = mb_substr($base, 0, 27).'-'.$suffix++;
        }

        return $candidate;
    }

    private function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }
}
