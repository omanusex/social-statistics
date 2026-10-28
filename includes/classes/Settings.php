<?php

/**
 * Simple key/value site settings stored in the `settings` table.
 */
class Settings
{
    public static function get(string $name, string $default = ''): string
    {
        return (string) (Database::value('SELECT value FROM settings WHERE name = ?', [$name]) ?? $default);
    }

    public static function set(string $name, string $value): void
    {
        Database::query(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, $value]
        );
    }
}
