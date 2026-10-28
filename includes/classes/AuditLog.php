<?php

/**
 * Records security-relevant actions (logins, imports, deletions, backups).
 */
class AuditLog
{
    public static function write(string $action, string $details = '', ?int $userId = null): void
    {
        Database::query(
            'INSERT INTO audit_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
            [$userId ?? Auth::id(), $action, mb_substr($details, 0, 500), client_ip()]
        );
    }

    public static function latest(int $limit = 50, int $offset = 0): array
    {
        return Database::all(
            'SELECT a.*, u.name AS user_name FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM audit_log');
    }
}
