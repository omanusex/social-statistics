<?php

/**
 * Creates a full SQL backup of the database that can be restored with phpMyAdmin
 * (Import tab) or the mysql command line.
 */
class BackupService
{
    private const TABLES = [
        'users', 'countries', 'platforms', 'metrics', 'sources', 'settings',
        'imports', 'stats', 'survey_responses', 'survey_response_platforms', 'audit_log',
    ];

    /** Columns that MySQL calculates itself and must not be inserted. */
    private const GENERATED = ['stats' => ['platform_key']];

    public static function dump(): string
    {
        $pdo = Database::conn();
        $out = "-- Backup of database `" . Database::name() . "` created " . date('Y-m-d H:i:s') . "\n"
             . "-- Restore: create the tables with database/schema.sql, then import this file.\n\n"
             . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach (self::TABLES as $table) {
            $out .= "DELETE FROM `$table`;\n";
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            foreach (array_chunk($rows, 200) as $chunk) {
                $skip = self::GENERATED[$table] ?? [];
                $columns = array_diff(array_keys($chunk[0]), $skip);
                $values = [];
                foreach ($chunk as $row) {
                    $values[] = '(' . implode(', ', array_map(
                        fn($c) => $row[$c] === null ? 'NULL' : $pdo->quote((string) $row[$c]),
                        $columns
                    )) . ')';
                }
                $out .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n" . implode(",\n", $values) . ";\n";
            }
            $out .= "\n";
        }
        return $out . "SET FOREIGN_KEY_CHECKS = 1;\n";
    }
}
