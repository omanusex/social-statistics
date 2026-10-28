<?php

/**
 * Reads the first sheet of a .csv or .xlsx file into an array of rows.
 * The .xlsx format is a ZIP of XML files, so no extra library is needed.
 */
class SpreadsheetReader
{
    /** @return array<int, array<int, string>> */
    public static function read(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'csv'  => self::readCsv($path),
            'xlsx' => self::readXlsx($path),
            default => throw new RuntimeException('Unsupported file type'),
        };
    }

    /** Rows as associative arrays keyed by the (lower-case) header row. */
    public static function readAssoc(string $path, string $extension): array
    {
        $rows = self::read($path, $extension);
        $header = array_map(fn($h) => strtolower(trim($h)), array_shift($rows) ?? []);
        $out = [];
        foreach ($rows as $row) {
            if (!array_filter($row, fn($v) => trim($v) !== '')) {
                continue; // skip empty lines
            }
            $row = array_pad(array_slice($row, 0, count($header)), count($header), '');
            $out[] = array_combine($header, array_map('trim', $row));
        }
        return ['header' => $header, 'rows' => $out];
    }

    private static function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // remove Excel BOM
        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row !== [null]) {
                $rows[] = array_map('strval', $row);
            }
        }
        fclose($handle);
        return $rows;
    }

    private static function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The Excel file could not be opened');
        }

        // Text cells point into the shared strings list
        $shared = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml !== false) {
            $doc = simplexml_load_string($xml);
            foreach ($doc->si as $si) {
                $text = isset($si->t) ? (string) $si->t : '';
                foreach ($si->r ?? [] as $run) {
                    $text .= (string) $run->t;
                }
                $shared[] = $text;
            }
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw new RuntimeException('The Excel file has no first sheet');
        }

        $rows = [];
        $doc = simplexml_load_string($sheet);
        foreach ($doc->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::columnIndex(preg_replace('/\d+/', '', (string) $c['r']));
                $type = (string) $c['t'];
                $value = match ($type) {
                    's'         => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default     => (string) $c->v,
                };
                $cells[$col] = $value;
            }
            if ($cells) {
                $line = array_fill(0, max(array_keys($cells)) + 1, '');
                foreach ($cells as $i => $v) {
                    $line[$i] = $v;
                }
                $rows[] = $line;
            }
        }
        return $rows;
    }

    /** "A" -> 0, "B" -> 1, "AA" -> 26 */
    private static function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }
        return $index - 1;
    }
}
