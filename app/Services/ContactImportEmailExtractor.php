<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use SimpleXMLElement;
use ZipArchive;

class ContactImportEmailExtractor
{
    /**
     * @return array<int, string>
     */
    public function fromUploadedFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            return $this->fromXlsx((string) $file->getRealPath());
        }

        $content = file_get_contents((string) $file->getRealPath());

        return $this->fromCsv(is_string($content) ? $content : '');
    }

    /**
     * @return array<int, string>
     */
    public function fromText(string $input): array
    {
        if (trim($input) === '') {
            return [];
        }

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $input, $matches);

        return array_values($matches[0] ?? []);
    }

    /**
     * @return array<int, string>
     */
    public function fromCsv(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $emails = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            foreach (str_getcsv($line) as $cell) {
                $emails = [...$emails, ...$this->fromText((string) $cell)];
            }
        }

        return $emails;
    }

    /**
     * @return array<int, string>
     */
    public function fromXlsx(string $path): array
    {
        if (! is_readable($path) || ! class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->readXlsxSharedStrings($zip);
        $emails = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);
            if (! is_string($entryName) || ! preg_match('#^xl/worksheets/sheet\d+\.xml$#', $entryName)) {
                continue;
            }

            $worksheetXml = $zip->getFromName($entryName);
            if (! is_string($worksheetXml) || trim($worksheetXml) === '') {
                continue;
            }

            $emails = [...$emails, ...$this->extractEmailsFromWorksheetXml($worksheetXml, $sharedStrings)];
        }

        $zip->close();

        return $emails;
    }

    /**
     * @return array<int, string>
     */
    private function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! is_string($xml) || trim($xml) === '') {
            return [];
        }

        $document = new SimpleXMLElement($xml);

        $strings = [];
        foreach ($document->xpath('//*[local-name()="si"]') ?: [] as $sharedStringItem) {
            $parts = $sharedStringItem->xpath('.//*[local-name()="t"]') ?: [];
            $strings[] = implode('', array_map(fn (SimpleXMLElement $part): string => (string) $part, $parts));
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, string>
     */
    private function extractEmailsFromWorksheetXml(string $xml, array $sharedStrings): array
    {
        $document = new SimpleXMLElement($xml);

        $emails = [];
        foreach ($document->xpath('//*[local-name()="c"]') ?: [] as $cell) {
            $value = $this->xlsxCellValue($cell, $sharedStrings);
            if ($value === '') {
                continue;
            }

            $emails = [...$emails, ...$this->fromText($value)];
        }

        return $emails;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 's') {
            $index = (int) ($cell->v ?? 0);

            return $sharedStrings[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            $parts = $cell->xpath('.//*[local-name()="t"]') ?: [];

            return implode('', array_map(fn (SimpleXMLElement $part): string => (string) $part, $parts));
        }

        return trim((string) ($cell->v ?? ''));
    }
}
