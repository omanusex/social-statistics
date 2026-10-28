<?php

/**
 * Single shared PDO connection to MySQL.
 * All queries in the project use prepared statements through this connection.
 */
class Database
{
    private static array $config = [];
    private static ?PDO $pdo = null;

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect(true);
        }
        return self::$pdo;
    }

    /** Connect to the server, optionally without selecting the database (used by the installer). */
    public static function connect(bool $withDatabase): PDO
    {
        $c = self::$config;
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $c['host'], $c['port'], $c['charset']);
        if ($withDatabase) {
            $dsn .= ';dbname=' . $c['name'];
        }
        return new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function name(): string
    {
        return self::$config['name'];
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = [])
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }
}
