<?php

/**
 * Brings data into the database from the three kinds of source:
 *  - StatCounter (live download or an uploaded CSV)
 *  - World Bank API
 *  - Excel / CSV files that follow the project templates (statistics or questionnaire)
 *
 * Every file is first parsed and validated (parse* methods, also used for the preview screen),
 * then saved (save* methods). Each run is recorded in the `imports` table so it can be undone.
 */
class DataImporter
{
    public const TYPE_STATCOUNTER = 'statcounter';
    public const TYPE_TEMPLATE    = 'template';
    public const TYPE_SURVEY      = 'survey';

    private ?int $userId;

    public function __construct(?int $userId)
    {
        $this->userId = $userId;
    }

    // ---- StatCounter --------------------------------------------------------

    /**
     * Download the latest data for the selected countries and devices.
     * @return array summary with inserted / updated / failed counts and messages
     */
    public function syncStatCounter(array $countries, array $devices, string $fromMonth, string $toMonth): array
    {
        $client = new StatCounterClient();
        $records = [];
        $messages = [];
        $failed = 0;

        foreach ($countries as $country) {
            foreach ($devices as $device) {
                try {
                    $csv = $client->fetchCsv($country['iso2'], $device, $fromMonth, $toMonth);
                    $parsed = $this->statCounterRecords(StatCounterClient::parse($csv), (int) $country['id'], $device);
                    array_push($records, ...$parsed);
                } catch (Throwable $ex) {
                    $failed++;
                    $messages[] = "{$country['name_en']} / $device: " . $ex->getMessage();
                }
            }
        }

        $summary = $this->saveStats($records, 'statcounter', 'api', null, $failed, $messages);
        Database::query('UPDATE sources SET last_synced_at = NOW() WHERE code = ?', ['statcounter']);
        return $summary;
    }

    public function parseStatCounterFile(string $path, int $countryId, string $device): array
    {
        if (!in_array($device, StatRepository::DEVICES, true)) {
            throw new RuntimeException('Unknown device');
        }
        $records = $this->statCounterRecords(StatCounterClient::parse(file_get_contents($path)), $countryId, $device);
        return ['records' => $records, 'errors' => []];
    }

    /** Converts parsed StatCounter months into stats records; unknown platforms are added to "Other". */
    private function statCounterRecords(array $months, int $countryId, string $device): array
    {
        $metricId = (int) StatRepository::metric('traffic_share')['id'];
        $map = array_column(
            Database::all('SELECT id, statcounter_name FROM platforms WHERE statcounter_name IS NOT NULL'),
            'id',
            'statcounter_name'
        );
        $map = array_change_key_case($map, CASE_LOWER);
        $otherId = $map['other'];

        $records = [];
        foreach ($months as $period => $values) {
            $byPlatform = [];
            foreach ($values as $name => $value) {
                $platformId = $map[strtolower($name)] ?? $otherId;
                $byPlatform[$platformId] = ($byPlatform[$platformId] ?? 0) + $value;
            }
            foreach ($byPlatform as $platformId => $value) {
                $records[] = [$countryId, $platformId, $metricId, $device, $period, round($value, 2)];
            }
        }
        return $records;
    }

    // ---- World Bank ---------------------------------------------------------

    public function syncWorldBank(int $fromYear, int $toYear): array
    {
        $client = new WorldBankClient();
        $countries = array_column(StatRepository::countries(), 'id', 'iso3');
        $records = [];
        $messages = [];
        $failed = 0;

        foreach (WorldBankClient::INDICATORS as $indicator => $metricCode) {
            $metricId = (int) StatRepository::metric($metricCode)['id'];
            try {
                foreach ($client->fetch($indicator, array_keys($countries), $fromYear, $toYear) as $row) {
                    if (isset($countries[$row['iso3']])) {
                        $records[] = [$countries[$row['iso3']], null, $metricId, 'all', $row['year'] . '-01-01', $row['value']];
                    }
                }
            } catch (Throwable $ex) {
                $failed++;
                $messages[] = "$indicator: " . $ex->getMessage();
            }
        }

        $summary = $this->saveStats($records, 'worldbank', 'api', null, $failed, $messages);
        Database::query('UPDATE sources SET last_synced_at = NOW() WHERE code = ?', ['worldbank']);
        return $summary;
    }

    // ---- Statistics template (Excel / CSV) -----------------------------------

    public const TEMPLATE_COLUMNS = ['country_iso2', 'platform', 'metric', 'device', 'period', 'value'];

    public function parseTemplate(string $path, string $extension): array
    {
        $sheet = SpreadsheetReader::readAssoc($path, $extension);
        $missing = array_diff(self::TEMPLATE_COLUMNS, $sheet['header']);
        if ($missing) {
            throw new RuntimeException('Missing columns: ' . implode(', ', $missing));
        }

        $countries = array_column(Database::all('SELECT id, iso2 FROM countries'), 'id', 'iso2');
        $platforms = array_column(Database::all('SELECT id, slug FROM platforms'), 'id', 'slug');
        $metrics = [];
        foreach (StatRepository::metrics() as $m) {
            $metrics[$m['code']] = $m;
        }

        $records = [];
        $errors = [];
        foreach ($sheet['rows'] as $i => $row) {
            $line = $i + 2; // +1 for the header, +1 because spreadsheets start at 1
            $metric = $metrics[strtolower($row['metric'])] ?? null;
            $country = $countries[strtoupper($row['country_iso2'])] ?? null;
            $device = strtolower($row['device'] ?: 'all');
            $value = str_replace([',', '%'], '', $row['value']);

            if (!$country)  { $errors[] = "Row $line: unknown country \"{$row['country_iso2']}\""; continue; }
            if (!$metric)   { $errors[] = "Row $line: unknown metric \"{$row['metric']}\""; continue; }
            if (!in_array($device, StatRepository::DEVICES, true)) { $errors[] = "Row $line: device must be all, mobile or desktop"; continue; }
            if (!is_numeric($value) || $value < 0) { $errors[] = "Row $line: value must be a positive number"; continue; }
            if ($metric['unit'] === '%' && $value > 100) { $errors[] = "Row $line: a percentage cannot be above 100"; continue; }

            $platformId = null;
            if ($metric['per_platform']) {
                $platformId = $platforms[strtolower($row['platform'])] ?? null;
                if (!$platformId) { $errors[] = "Row $line: unknown platform \"{$row['platform']}\""; continue; }
            }
            $period = StatRepository::normalisePeriod($row['period'], $metric['granularity']);
            if (!$period) { $errors[] = "Row $line: period must look like 2025 or 2025-03"; continue; }

            $records[] = [$country, $platformId, (int) $metric['id'], $device, $period, (float) $value];
        }
        return ['records' => $records, 'errors' => $errors];
    }

    // ---- Questionnaire (Excel / CSV) ----------------------------------------

    public const SURVEY_COLUMNS = ['age_group', 'gender', 'platforms_used', 'favourite', 'main_purpose', 'daily_hours', 'late_night_use'];

    public function parseSurvey(string $path, string $extension): array
    {
        $sheet = SpreadsheetReader::readAssoc($path, $extension);
        $missing = array_diff(self::SURVEY_COLUMNS, $sheet['header']);
        if ($missing) {
            throw new RuntimeException('Missing columns: ' . implode(', ', $missing));
        }

        $records = [];
        $errors = [];
        foreach ($sheet['rows'] as $i => $row) {
            $data = [
                'age_group'      => $row['age_group'],
                'gender'         => strtolower($row['gender']),
                'platforms'      => array_values(array_filter(array_map('trim', explode(';', strtolower($row['platforms_used']))))),
                'favourite'      => strtolower($row['favourite']),
                'main_purpose'   => strtolower($row['main_purpose']),
                'daily_hours'    => $row['daily_hours'],
                'late_night_use' => in_array(strtolower($row['late_night_use']), ['1', 'yes', 'true'], true) ? '1' : '0',
            ];
            $problems = SurveyRepository::validate($data);
            if ($problems) {
                $errors[] = 'Row ' . ($i + 2) . ': ' . implode(', ', array_map('t', $problems));
                continue;
            }
            $records[] = $data;
        }
        return ['records' => $records, 'errors' => $errors];
    }

    public function saveSurvey(array $records, string $filename, int $failed, bool $replace): array
    {
        $importId = $this->startImport('survey', 'file', $filename);
        $pdo = Database::conn();
        $pdo->beginTransaction();
        try {
            if ($replace) {
                SurveyRepository::deleteAll();
            }
            foreach ($records as $data) {
                SurveyRepository::add($data, 'import', $importId);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            Database::query("UPDATE imports SET status = 'failed', message = ? WHERE id = ?", [$ex->getMessage(), $importId]);
            throw $ex;
        }
        return $this->finishImport($importId, count($records), 0, $failed, []);
    }

    // ---- Saving -------------------------------------------------------------

    /**
     * Writes stats records inside one transaction and records the import.
     * @param array $records list of [country_id, platform_id, metric_id, device, period, value]
     */
    public function saveStats(array $records, string $sourceCode, string $type, ?string $filename, int $failed = 0, array $messages = []): array
    {
        $importId = $this->startImport($sourceCode, $type, $filename);
        $sourceId = (int) StatRepository::source($sourceCode)['id'];
        $inserted = $updated = 0;

        $pdo = Database::conn();
        $pdo->beginTransaction();
        try {
            foreach ($records as [$countryId, $platformId, $metricId, $device, $period, $value]) {
                $result = StatRepository::upsert($countryId, $platformId, $metricId, $device, $period, $value, $sourceId, $importId);
                if ($result === 'inserted') $inserted++;
                if ($result === 'updated')  $updated++;
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            Database::query("UPDATE imports SET status = 'failed', message = ? WHERE id = ?", [$ex->getMessage(), $importId]);
            throw $ex;
        }
        return $this->finishImport($importId, $inserted, $updated, $failed, $messages);
    }

    private function startImport(string $sourceCode, string $type, ?string $filename): int
    {
        Database::query(
            "INSERT INTO imports (user_id, source_id, type, filename, status) VALUES (?, ?, ?, ?, 'success')",
            [$this->userId, StatRepository::source($sourceCode)['id'], $type, $filename]
        );
        return (int) Database::conn()->lastInsertId();
    }

    private function finishImport(int $importId, int $inserted, int $updated, int $failed, array $messages): array
    {
        $status = $failed === 0 ? 'success' : ($inserted + $updated > 0 ? 'partial' : 'failed');
        $message = implode("\n", array_slice($messages, 0, 20));
        Database::query(
            'UPDATE imports SET rows_inserted = ?, rows_updated = ?, rows_failed = ?, status = ?, message = ? WHERE id = ?',
            [$inserted, $updated, $failed, $status, $message ?: null, $importId]
        );
        AuditLog::write('import', "Import #$importId: $inserted inserted, $updated updated, $failed failed", $this->userId);
        return compact('importId', 'inserted', 'updated', 'failed', 'status', 'messages');
    }

    /** Undo an import: delete the values it inserted or changed. */
    public static function rollback(int $importId): int
    {
        $deleted = Database::query('DELETE FROM stats WHERE import_id = ?', [$importId])->rowCount();
        $deleted += Database::query('DELETE FROM survey_responses WHERE import_id = ?', [$importId])->rowCount();
        AuditLog::write('import_rollback', "Import #$importId rolled back, $deleted rows deleted");
        return $deleted;
    }

    public static function recent(int $limit = 20): array
    {
        return Database::all(
            'SELECT i.*, s.name AS source_name, u.name AS user_name,
                    (SELECT COUNT(*) FROM stats st WHERE st.import_id = i.id)
                  + (SELECT COUNT(*) FROM survey_responses r WHERE r.import_id = i.id) AS rows_now
             FROM imports i
             JOIN sources s ON s.id = i.source_id
             LEFT JOIN users u ON u.id = i.user_id
             ORDER BY i.id DESC LIMIT ' . (int) $limit
        );
    }
}
