<?php

/**
 * Reads yearly indicators from the World Bank Open Data REST API (JSON, no key needed).
 * Documentation: https://datahelpdesk.worldbank.org/knowledgebase/articles/889392
 */
class WorldBankClient
{
    private const URL = 'https://api.worldbank.org/v2/country/%s/indicator/%s';

    /** Indicator code => metric code in our database */
    public const INDICATORS = [
        'IT.NET.USER.ZS' => 'internet_users',
        'SP.POP.TOTL'    => 'population',
    ];

    /**
     * @param string[] $iso3Codes
     * @return array<int, array{iso3: string, year: int, value: float}>
     */
    public function fetch(string $indicator, array $iso3Codes, int $fromYear, int $toYear): array
    {
        $url = sprintf(self::URL, implode(';', $iso3Codes), $indicator)
             . '?' . http_build_query(['format' => 'json', 'date' => "$fromYear:$toYear", 'per_page' => 2000]);

        $json = json_decode(HttpClient::get($url), true);
        if (!is_array($json) || !isset($json[1]) || !is_array($json[1])) {
            $message = $json[0]['message'][0]['value'] ?? 'Unexpected response';
            throw new RuntimeException('World Bank API: ' . $message);
        }

        $rows = [];
        foreach ($json[1] as $item) {
            if ($item['value'] === null) {
                continue; // the year has not been published yet
            }
            $rows[] = [
                'iso3'  => $item['countryiso3code'],
                'year'  => (int) $item['date'],
                'value' => (float) $item['value'],
            ];
        }
        return $rows;
    }
}
