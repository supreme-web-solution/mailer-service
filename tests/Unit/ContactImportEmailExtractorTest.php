<?php

namespace Tests\Unit;

use App\Services\ContactImportEmailExtractor;
use Tests\TestCase;
use ZipArchive;

class ContactImportEmailExtractorTest extends TestCase
{
    public function test_extracts_emails_from_xlsx_cells(): void
    {
        $path = $this->createXlsxFixture([
            ['Name', 'xlsx-one@example.com'],
            ['Other', 'xlsx-two@example.com'],
        ]);

        $emails = (new ContactImportEmailExtractor)->fromXlsx($path);

        @unlink($path);

        $this->assertEqualsCanonicalizing(
            ['xlsx-one@example.com', 'xlsx-two@example.com'],
            $emails,
        );
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function createXlsxFixture(array $rows): string
    {
        $sharedStrings = [];
        $sharedStringIndex = [];

        $resolveSharedString = function (string $value) use (&$sharedStrings, &$sharedStringIndex): int {
            if (! array_key_exists($value, $sharedStringIndex)) {
                $sharedStringIndex[$value] = count($sharedStrings);
                $sharedStrings[] = $value;
            }

            return $sharedStringIndex[$value];
        };

        $sheetRows = '';
        foreach ($rows as $rowNumber => $cells) {
            $rowIndex = $rowNumber + 1;
            $sheetCells = '';
            foreach ($cells as $columnIndex => $value) {
                $columnLetter = chr(ord('A') + $columnIndex);
                $sharedIndex = $resolveSharedString($value);
                $sheetCells .= '<c r="'.$columnLetter.$rowIndex.'" t="s"><v>'.$sharedIndex.'</v></c>';
            }
            $sheetRows .= '<row r="'.$rowIndex.'">'.$sheetCells.'</row>';
        }

        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">';
        foreach ($sharedStrings as $value) {
            $sharedStringsXml .= '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>';
        }
        $sharedStringsXml .= '</sst>';

        $worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$sheetRows.'</sheetData>'
            .'</worksheet>';

        $path = tempnam(sys_get_temp_dir(), 'contacts_');
        $this->assertNotFalse($path);
        @unlink($path);
        $path .= '.xlsx';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->close();

        return $path;
    }
}
