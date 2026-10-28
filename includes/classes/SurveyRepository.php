<?php

/**
 * Local study: questionnaire responses and their summaries.
 */
class SurveyRepository
{
    public const AGE_GROUPS = ['13-15', '16-18', '19-21', '22-25'];
    public const GENDERS    = ['female', 'male'];
    public const PURPOSES   = ['education', 'entertainment', 'communication', 'news'];
    public const HOURS      = ['<1', '1-2', '3-4', '5+'];

    /** Platforms a respondent can choose in the questionnaire. */
    public const PLATFORM_SLUGS = ['snapchat', 'youtube', 'tiktok', 'instagram', 'whatsapp', 'x', 'facebook'];

    public static function surveyPlatforms(): array
    {
        $in = implode(',', array_fill(0, count(self::PLATFORM_SLUGS), '?'));
        return Database::all("SELECT * FROM platforms WHERE slug IN ($in) ORDER BY sort_order", self::PLATFORM_SLUGS);
    }

    /**
     * Validates and saves one response. Returns a list of error keys (empty = saved).
     */
    public static function add(array $data, string $origin, ?int $importId = null): array
    {
        $errors = self::validate($data);
        if ($errors) {
            return $errors;
        }
        $platforms = Database::all(
            'SELECT id, slug FROM platforms WHERE slug IN (' . implode(',', array_fill(0, count($data['platforms']), '?')) . ')',
            $data['platforms']
        );
        $ids = array_column($platforms, 'id', 'slug');
        $favouriteId = $ids[$data['favourite']] ?? null;

        $pdo = Database::conn();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        try {
            Database::query(
                'INSERT INTO survey_responses (age_group, gender, favourite_id, main_purpose, daily_hours, late_night_use, origin, import_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$data['age_group'], $data['gender'], $favouriteId, $data['main_purpose'], $data['daily_hours'], (int) $data['late_night_use'], $origin, $importId]
            );
            $responseId = (int) $pdo->lastInsertId();
            foreach ($ids as $platformId) {
                Database::query('INSERT INTO survey_response_platforms (response_id, platform_id) VALUES (?, ?)', [$responseId, $platformId]);
            }
            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $ex) {
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            throw $ex;
        }
        return [];
    }

    public static function validate(array $data): array
    {
        $errors = [];
        if (!in_array($data['age_group'] ?? '', self::AGE_GROUPS, true))      $errors[] = 'survey.err_age';
        if (!in_array($data['gender'] ?? '', self::GENDERS, true))           $errors[] = 'survey.err_gender';
        if (!in_array($data['main_purpose'] ?? '', self::PURPOSES, true))    $errors[] = 'survey.err_purpose';
        if (!in_array($data['daily_hours'] ?? '', self::HOURS, true))        $errors[] = 'survey.err_hours';
        if (!in_array((string) ($data['late_night_use'] ?? ''), ['0', '1'], true)) $errors[] = 'survey.err_late';

        $platforms = $data['platforms'] ?? [];
        if (!is_array($platforms) || !$platforms || array_diff($platforms, self::PLATFORM_SLUGS)) {
            $errors[] = 'survey.err_platforms';
        } elseif (!in_array($data['favourite'] ?? '', $platforms, true)) {
            $errors[] = 'survey.err_favourite';
        }
        return $errors;
    }

    /** WHERE clause for the optional age / gender filters. */
    private static function filterSql(array $filters, string $alias = 'r'): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($filters['age'] ?? '', self::AGE_GROUPS, true)) {
            $where[] = "$alias.age_group = ?";
            $params[] = $filters['age'];
        }
        if (in_array($filters['gender'] ?? '', self::GENDERS, true)) {
            $where[] = "$alias.gender = ?";
            $params[] = $filters['gender'];
        }
        return [implode(' AND ', $where), $params];
    }

    public static function count(array $filters = []): int
    {
        [$where, $params] = self::filterSql($filters);
        return (int) Database::value("SELECT COUNT(*) FROM survey_responses r WHERE $where", $params);
    }

    /** All the numbers the Local Study page needs. */
    public static function summary(array $filters = []): array
    {
        [$where, $params] = self::filterSql($filters);
        $total = self::count($filters);

        $usage = Database::all(
            "SELECT p.slug, p.name, p.color, COUNT(*) AS n
             FROM survey_response_platforms sp
             JOIN survey_responses r ON r.id = sp.response_id
             JOIN platforms p ON p.id = sp.platform_id
             WHERE $where GROUP BY p.id ORDER BY n DESC",
            $params
        );
        $favourite = Database::all(
            "SELECT p.slug, p.name, p.color, COUNT(*) AS n
             FROM survey_responses r JOIN platforms p ON p.id = r.favourite_id
             WHERE $where GROUP BY p.id ORDER BY n DESC",
            $params
        );
        $group = fn(string $column, array $order) => self::groupCount($column, $order, $where, $params);

        $lateNight = (int) Database::value("SELECT SUM(late_night_use) FROM survey_responses r WHERE $where", $params);

        return [
            'total'      => $total,
            'usage'      => array_map(fn($r) => $r + ['pct' => $total ? round($r['n'] * 100 / $total, 1) : 0], $usage),
            'favourite'  => $favourite,
            'purpose'    => $group('main_purpose', self::PURPOSES),
            'hours'      => $group('daily_hours', self::HOURS),
            'age'        => $group('age_group', self::AGE_GROUPS),
            'late_night' => $total ? round($lateNight * 100 / $total, 1) : 0,
            'heavy_use'  => $total ? round(self::groupCount('daily_hours', ['5+'], $where, $params)['5+'] * 100 / $total, 1) : 0,
        ];
    }

    private static function groupCount(string $column, array $order, string $where, array $params): array
    {
        $rows = Database::all("SELECT r.$column AS k, COUNT(*) AS n FROM survey_responses r WHERE $where GROUP BY r.$column", $params);
        $counts = array_fill_keys($order, 0);
        foreach ($rows as $row) {
            $counts[$row['k']] = (int) $row['n'];
        }
        return $counts;
    }

    public static function deleteAll(): int
    {
        return Database::query('DELETE FROM survey_responses')->rowCount();
    }

    /** All responses as rows for the CSV download. */
    public static function export(): array
    {
        return Database::all(
            "SELECT r.id, r.age_group, r.gender,
                    (SELECT GROUP_CONCAT(p.slug ORDER BY p.slug SEPARATOR ';')
                     FROM survey_response_platforms sp JOIN platforms p ON p.id = sp.platform_id
                     WHERE sp.response_id = r.id) AS platforms_used,
                    f.slug AS favourite, r.main_purpose, r.daily_hours, r.late_night_use, r.origin, r.created_at
             FROM survey_responses r LEFT JOIN platforms f ON f.id = r.favourite_id
             ORDER BY r.id"
        );
    }
}
