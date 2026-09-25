<?php

namespace App\Support;

use DateTimeInterface;
use RuntimeException;
use ZipArchive;

/**
 * A dependency-free .xlsx writer for exports (REP-03, spec 0003 Part D).
 *
 * An .xlsx file is a zip of SpreadsheetML parts. This writes the six parts a
 * reader needs and nothing else: one sheet, inline strings (no shared string
 * table to hold in memory), numbers as numbers, booleans as booleans, a bold
 * header row, and every value XML-escaped. The sheet is streamed to a temp
 * file row by row, so memory does not grow with the export (`lazyById()`
 * upstream, a generator here).
 *
 * It replaces a spreadsheet package on purpose: AGENTS.md asks for a
 * streamed, dependency-free export first and an XLSX library only if the
 * client needs more than a flat sheet.
 */
final class SimpleXlsxWriter
{
    private const SHEET_NAME_MAX = 31;

    /**
     * Write headers and rows to a temporary .xlsx and return its path.
     *
     * The caller owns the file: send it with `response()->download()` and
     * `deleteFileAfterSend()`, or unlink it.
     *
     * @param  list<string>  $headers
     * @param  iterable<int, array<int|string, mixed>>  $rows  one array of cell values per row, in header order
     */
    public static function fromRows(array $headers, iterable $rows, string $sheetName = 'Export'): string
    {
        $path = self::temporaryPath('guesvia-export-', '.xlsx');
        $sheetPath = self::temporaryPath('guesvia-sheet-', '.xml');

        self::writeSheet($sheetPath, $headers, $rows);

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($sheetPath);

            throw new RuntimeException("Could not create the export at [{$path}].");
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRelationships());
        $zip->addFromString('xl/workbook.xml', self::workbook(self::sheetName($sheetName)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelationships());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');

        if (! $zip->close()) {
            @unlink($sheetPath);

            throw new RuntimeException("Could not finish the export at [{$path}].");
        }

        @unlink($sheetPath);

        return $path;
    }

    /**
     * The spreadsheet cell reference for a zero-based column: 0 => A, 26 => AA.
     */
    public static function columnLetter(int $index): string
    {
        $letters = '';
        $index++;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    // ------------------------------------------------------------------ sheet

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, array<int|string, mixed>>  $rows
     */
    private static function writeSheet(string $sheetPath, array $headers, iterable $rows): void
    {
        $handle = fopen($sheetPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Could not open the sheet buffer at [{$sheetPath}].");
        }

        fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n");
        fwrite($handle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');

        $rowNumber = 1;

        if ($headers !== []) {
            fwrite($handle, self::row($rowNumber, $headers, true));
            $rowNumber++;
        }

        foreach ($rows as $row) {
            fwrite($handle, self::row($rowNumber, array_values($row), false));
            $rowNumber++;
        }

        fwrite($handle, '</sheetData></worksheet>');
        fclose($handle);
    }

    /**
     * @param  list<mixed>  $values
     */
    private static function row(int $rowNumber, array $values, bool $bold): string
    {
        $cells = '';

        foreach ($values as $index => $value) {
            $cell = self::cell(self::columnLetter($index).$rowNumber, $value, $bold);

            if ($cell !== null) {
                $cells .= $cell;
            }
        }

        return '<row r="'.$rowNumber.'">'.$cells.'</row>';
    }

    /**
     * One cell, or null for an empty one (a missing cell reads as blank).
     */
    private static function cell(string $reference, mixed $value, bool $bold): ?string
    {
        $style = $bold ? ' s="1"' : '';

        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return '<c r="'.$reference.'"'.$style.' t="b"><v>'.($value ? '1' : '0').'</v></c>';
        }

        if (is_int($value) || (is_float($value) && is_finite($value))) {
            return '<c r="'.$reference.'"'.$style.'><v>'.self::number($value).'</v></c>';
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        } elseif (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $value = $encoded === false ? '' : $encoded;
        } elseif (! is_string($value)) {
            $value = (string) $value;
        }

        return '<c r="'.$reference.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'
            .self::escape($value)
            .'</t></is></c>';
    }

    private static function number(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        // Locale-independent, no exponent for ordinary magnitudes.
        $formatted = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }

    /**
     * XML-escape and drop the control characters XML 1.0 forbids.
     */
    private static function escape(string $value): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function sheetName(string $name): string
    {
        $clean = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', trim($name));
        $clean = trim(mb_substr($clean, 0, self::SHEET_NAME_MAX));

        return $clean === '' ? 'Export' : $clean;
    }

    // ------------------------------------------------------------------ parts

    private static function temporaryPath(string $prefix, string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), $prefix);

        if ($base === false) {
            throw new RuntimeException('Could not allocate a temporary file for the export.');
        }

        $path = $base.$extension;

        // tempnam() created $base; the extension is what Excel keys on.
        if (! @rename($base, $path)) {
            @unlink($base);

            throw new RuntimeException("Could not name the temporary export [{$path}].");
        }

        return $path;
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::escape($sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /**
     * The smallest valid stylesheet: style 0 is plain, style 1 is bold.
     */
    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
