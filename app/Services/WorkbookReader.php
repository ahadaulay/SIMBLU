<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class WorkbookReader
{
    private function xml(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $content = $zip->getFromName($path);
        if ($content === false || strlen($content) > 20000000 || stripos($content, '<!DOCTYPE') !== false) {
            throw ValidationException::withMessages(['file' => 'Struktur workbook tidak didukung atau terlalu besar.']);
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($xml === false) {
            throw ValidationException::withMessages(['file' => 'XML workbook tidak valid.']);
        }

        return $xml;
    }

    public function read(string $path, ?string $sheetName = null): array
    {
        $temporary = null;
        if (PHP_OS_FAMILY === 'Windows' && strlen($path) > 240) {
            $temporary = tmpfile();
            $input = fopen($path, 'rb');
            stream_copy_to_stream($input, $temporary);
            fclose($input);
            $path = stream_get_meta_data($temporary)['uri'];
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Gunakan file .xlsx yang valid.']);
        }
        try {
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }
            if ($size > 50000000 || $zip->numFiles > 2000) {
                throw ValidationException::withMessages(['file' => 'Workbook terlalu besar untuk impor interaktif. Pisahkan per unit.']);
            }
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $relations = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $targets = [];
            foreach ($relations->Relationship as $relation) {
                $targets[(string) $relation['Id']] = (string) $relation['Target'];
            }
            $sheets = [];
            $target = null;
            foreach ($workbook->sheets->sheet as $sheet) {
                $name = (string) $sheet['name'];
                $sheets[] = $name;
                if ($name === $sheetName) {
                    $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                    $target = $targets[$id] ?? null;
                }
            }
            if ($sheetName === null) {
                return ['sheets' => $sheets, 'cells' => []];
            }
            if (! $target || ! preg_match('~^(?:/xl/)?worksheets/[A-Za-z0-9_.-]+\.xml$~', $target)) {
                throw ValidationException::withMessages(['sheetName' => 'Sheet tidak ditemukan atau format tidak didukung.']);
            }
            $shared = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->si as $item) {
                    $text = (string) $item->t;
                    foreach ($item->r as $run) {
                        $text .= (string) $run->t;
                    } $shared[] = $text;
                }
            }
            $xml = $this->xml($zip, str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target);
            $cells = [];
            foreach ($xml->sheetData->row as $row) {
                foreach ($row->c as $cell) {
                    $type = (string) $cell['t'];
                    $value = match ($type) {
                        's' => $shared[(int) $cell->v] ?? '', 'inlineStr' => (string) $cell->is->t, default => (string) $cell->v
                    };
                    $cells[(string) $cell['r']] = ['value' => $value, 'formula' => isset($cell->f), 'error' => $type === 'e'];
                }
            }

            return ['sheets' => $sheets, 'cells' => $cells];
        } finally {
            $zip->close();
            if ($temporary) {
                fclose($temporary);
            }
        }
    }
}
