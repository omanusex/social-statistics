<?php

/**
 * Downloads monthly social media share data (CSV) from StatCounter Global Stats.
 * Data licence: CC BY-SA 3.0 - the source is credited on every page that shows it.
 */
class StatCounterClient
{
    private const URL = 'https://gs.statcounter.com/chart.php';

    private const DEVICE_PARAM = [
        'all'     => 'desktop+mobile+tablet+console',
        'mobile'  => 'mobile',
        'desktop' => 'desktop',
    ];

    /**
     * @return string the raw CSV text
     */
    public function fetchCsv(string $iso2, string $device, string $fromMonth, string $toMonth): string
    {
        $region = $iso2 === 'WW' ? 'ww' : $iso2;
        $query = http_build_query([
            'device'          => $device,
            'device_hidden'   => self::DEVICE_PARAM[$device],
            'multi-device'    => 'true',
            'statType_hidden' => 'social_media',
            'region_hidden'   => $region,
            'granularity'     => 'monthly',
            'statType'        => 'Social Media',
            'region'          => $region,
            'fromInt'         => str_replace('-', '', $fromMonth),
            'toInt'           => str_replace('-', '', $toMonth),
            'fromMonthYear'   => $fromMonth,
            'toMonthYear'     => $toMonth,
            'csv'             => '1',
        ]);
        return HttpClient::get(self::URL . '?' . $query);
    }

    /**
     * Turns StatCounter CSV text into [ 'YYYY-MM-01' => [ 'Facebook' => 52.8, ... ], ... ].
     * Months where every value is 0 (future months) are skipped.
     */
    public static function parse(string $csv): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($csv))));
        if (!$lines) {
            return [];
        }
        $header = str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]), ',', '"', '\\');
        if (strtolower(trim($header[0] ?? '')) !== 'date') {
            throw new RuntimeException('This is not a StatCounter CSV file (first column must be "Date")');
        }

        $data = [];
        foreach (array_slice($lines, 1) as $line) {
            $cells = str_getcsv($line, ',', '"', '\\');
            if (!preg_match('/^(\d{4})-(\d{2})$/', trim($cells[0] ?? ''), $m)) {
                continue;
            }
            $values = [];
            foreach (array_slice($header, 1) as $i => $name) {
                $values[trim($name)] = (float) ($cells[$i + 1] ?? 0);
            }
            if (array_sum($values) > 0) {
                $data["{$m[1]}-{$m[2]}-01"] = $values;
            }
        }
        return $data;
    }
}
