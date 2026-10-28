<?php

/**
 * All read/write queries for countries, platforms, statistic types and statistic values.
 */
class StatRepository
{
    public const DEVICES = ['all', 'mobile', 'desktop'];

    // ---- Reference data -----------------------------------------------------

    public static function countries(): array
    {
        return Database::all(
            "SELECT * FROM countries WHERE is_active = 1
             ORDER BY FIELD(region, 'GCC', 'Arab', 'World'), is_default DESC, name_en"
        );
    }

    public static function country(string $iso2): ?array
    {
        return Database::one('SELECT * FROM countries WHERE iso2 = ? AND is_active = 1', [strtoupper($iso2)]);
    }

    public static function defaultCountry(): array
    {
        return Database::one('SELECT * FROM countries WHERE is_default = 1 LIMIT 1')
            ?? Database::one('SELECT * FROM countries ORDER BY id LIMIT 1');
    }

    /** Country from ?country=XX, falling back to the default country (Oman). */
    public static function countryFromRequest(string $param = 'country'): array
    {
        $iso = input($param);
        return ($iso !== '' ? self::country($iso) : null) ?? self::defaultCountry();
    }

    public static function platforms(): array
    {
        return Database::all('SELECT * FROM platforms ORDER BY sort_order, name');
    }

    public static function platform(string $slug): ?array
    {
        return Database::one('SELECT * FROM platforms WHERE slug = ?', [$slug]);
    }

    /** Platforms that have at least one value for the metric. */
    public static function platformsWithData(int $metricId): array
    {
        return Database::all(
            'SELECT p.* FROM platforms p
             WHERE EXISTS (SELECT 1 FROM stats s WHERE s.platform_id = p.id AND s.metric_id = ?)
             ORDER BY p.sort_order, p.name',
            [$metricId]
        );
    }

    public static function metrics(bool $onlyWithData = false): array
    {
        $sql = 'SELECT * FROM metrics';
        if ($onlyWithData) {
            $sql .= ' WHERE EXISTS (SELECT 1 FROM stats s WHERE s.metric_id = metrics.id)';
        }
        return Database::all($sql . ' ORDER BY id');
    }

    public static function metric(string $code): ?array
    {
        return Database::one('SELECT * FROM metrics WHERE code = ?', [$code]);
    }

    public static function sources(): array
    {
        return Database::all('SELECT * FROM sources ORDER BY id');
    }

    public static function source(string $code): array
    {
        return Database::one('SELECT * FROM sources WHERE code = ?', [$code]);
    }

    // ---- Periods ------------------------------------------------------------

    public static function latestPeriod(int $metricId, string $device = 'all', ?int $countryId = null): ?string
    {
        $sql = 'SELECT MAX(period) FROM stats WHERE metric_id = ? AND device = ?';
        $params = [$metricId, $device];
        if ($countryId) {
            $sql .= ' AND country_id = ?';
            $params[] = $countryId;
        }
        return Database::value($sql, $params);
    }

    public static function periodRange(int $metricId): array
    {
        $row = Database::one('SELECT MIN(period) AS first, MAX(period) AS last FROM stats WHERE metric_id = ?', [$metricId]);
        return [$row['first'] ?? null, $row['last'] ?? null];
    }

    /** List of all periods (Y-m-01) between two dates, step = month or year. */
    public static function periodsBetween(string $from, string $to, string $granularity): array
    {
        $periods = [];
        $step = $granularity === 'yearly' ? '+1 year' : '+1 month';
        for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime($step, $d)) {
            $periods[] = date('Y-m-d', $d);
        }
        return $periods;
    }

    /** Converts "2025-03" or "2025" from a form into a period date. */
    public static function normalisePeriod(string $value, string $granularity): ?string
    {
        if (preg_match('/^(\d{4})(?:-(\d{1,2}))?/', $value, $m)) {
            $month = $granularity === 'yearly' ? 1 : (int) ($m[2] ?? 1);
            if ($month >= 1 && $month <= 12) {
                return sprintf('%04d-%02d-01', $m[1], $month);
            }
        }
        return null;
    }

    // ---- Values -------------------------------------------------------------

    /**
     * Time series for one country. Returns rows of period, platform_id, value.
     * $platformIds = [] means every platform (or the country-level value for metrics without platforms).
     */
    public static function series(int $countryId, int $metricId, string $device, string $from, string $to, array $platformIds = []): array
    {
        $sql = 'SELECT period, platform_id, value FROM stats
                WHERE country_id = ? AND metric_id = ? AND device = ? AND period BETWEEN ? AND ?';
        $params = [$countryId, $metricId, $device, $from, $to];
        if ($platformIds) {
            $sql .= ' AND platform_id IN (' . implode(',', array_fill(0, count($platformIds), '?')) . ')';
            array_push($params, ...$platformIds);
        }
        return Database::all($sql . ' ORDER BY period', $params);
    }

    /** One value per country for the same platform and period (rankings, scatter chart). */
    public static function valuesByCountry(int $metricId, ?int $platformId, string $device, string $period): array
    {
        $sql = 'SELECT c.iso2, c.name_en, c.name_ar, c.region, c.is_default, s.value
                FROM stats s JOIN countries c ON c.id = s.country_id
                WHERE s.metric_id = ? AND s.device = ? AND s.period = ? AND c.is_active = 1 AND ';
        $params = [$metricId, $device, $period];
        if ($platformId) {
            $sql .= 's.platform_id = ?';
            $params[] = $platformId;
        } else {
            $sql .= 's.platform_id IS NULL';
        }
        return Database::all($sql . ' ORDER BY s.value DESC', $params);
    }

    /** Platform shares for one country and period, highest first. */
    public static function shares(int $countryId, int $metricId, string $device, string $period): array
    {
        return Database::all(
            'SELECT p.id, p.slug, p.name, p.color, s.value
             FROM stats s JOIN platforms p ON p.id = s.platform_id
             WHERE s.country_id = ? AND s.metric_id = ? AND s.device = ? AND s.period = ?
             ORDER BY s.value DESC',
            [$countryId, $metricId, $device, $period]
        );
    }

    /** The most recent value of a country-level metric (e.g. internet users %). */
    public static function latestCountryValue(int $countryId, string $metricCode): ?array
    {
        return Database::one(
            'SELECT s.period, s.value FROM stats s JOIN metrics m ON m.id = s.metric_id
             WHERE s.country_id = ? AND m.code = ? AND s.platform_id IS NULL
             ORDER BY s.period DESC LIMIT 1',
            [$countryId, $metricCode]
        );
    }

    /** Leading platform (excluding "Other") in every country for one period – used by the map. */
    public static function topPlatformPerCountry(int $metricId, string $device, string $period): array
    {
        return Database::all(
            "SELECT c.iso2, c.name_en, c.name_ar, p.slug, p.name AS platform, p.color, s.value
             FROM stats s
             JOIN countries c ON c.id = s.country_id
             JOIN platforms p ON p.id = s.platform_id
             WHERE s.metric_id = ? AND s.device = ? AND s.period = ? AND p.slug <> 'other'
               AND s.value = (
                   SELECT MAX(s2.value) FROM stats s2 JOIN platforms p2 ON p2.id = s2.platform_id
                   WHERE s2.country_id = s.country_id AND s2.metric_id = s.metric_id
                     AND s2.device = s.device AND s2.period = s.period AND p2.slug <> 'other')",
            [$metricId, $device, $period]
        );
    }

    /**
     * Insert or update one value.
     * Returns 'inserted', 'updated' or 'unchanged'.
     */
    public static function upsert(int $countryId, ?int $platformId, int $metricId, string $device, string $period, float $value, int $sourceId, ?int $importId): string
    {
        $stmt = Database::query(
            'INSERT INTO stats (country_id, platform_id, metric_id, device, period, value, source_id, import_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                import_id = IF(value <> VALUES(value), VALUES(import_id), import_id),
                source_id = VALUES(source_id),
                value = VALUES(value)',
            [$countryId, $platformId, $metricId, $device, $period, $value, $sourceId, $importId]
        );
        return match ($stmt->rowCount()) {
            1 => 'inserted',
            2 => 'updated',
            default => 'unchanged',
        };
    }

    public static function totals(): array
    {
        return Database::one(
            'SELECT COUNT(*) AS rows_total,
                    COUNT(DISTINCT country_id) AS countries,
                    COUNT(DISTINCT platform_id) AS platforms,
                    MAX(updated_at) AS last_update
             FROM stats'
        );
    }

    // ---- Calculations used by charts ---------------------------------------

    /** 3-month moving average (null gaps are skipped). */
    public static function movingAverage(array $values, int $window = 3): array
    {
        $out = [];
        foreach ($values as $i => $v) {
            $slice = array_filter(array_slice($values, max(0, $i - $window + 1), min($window, $i + 1)), fn($x) => $x !== null);
            $out[] = ($v === null || !$slice) ? null : round(array_sum($slice) / count($slice), 2);
        }
        return $out;
    }

    /** Pearson correlation coefficient of two equally long lists. */
    public static function correlation(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n < 3) {
            return null;
        }
        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $sxy = $sxx = $syy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $mx;
            $dy = $y[$i] - $my;
            $sxy += $dx * $dy;
            $sxx += $dx * $dx;
            $syy += $dy * $dy;
        }
        return ($sxx == 0 || $syy == 0) ? null : round($sxy / sqrt($sxx * $syy), 2);
    }
}
