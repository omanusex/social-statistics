<?php

/**
 * English / Arabic translations.
 * The chosen language is kept in a cookie and can be changed with ?lang=en or ?lang=ar.
 */
class Lang
{
    private const SUPPORTED = ['en', 'ar'];

    private static string $current = 'en';
    private static array $strings = [];
    private static array $fallback = [];

    public static function init(string $default): void
    {
        $lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? $default;
        if (!in_array($lang, self::SUPPORTED, true)) {
            $lang = $default;
        }
        if (isset($_GET['lang']) && ($_COOKIE['lang'] ?? '') !== $lang) {
            setcookie('lang', $lang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        }
        self::$current  = $lang;
        self::$fallback = require ROOT_PATH . '/lang/en.php';
        self::$strings  = $lang === 'en' ? self::$fallback : require ROOT_PATH . "/lang/{$lang}.php";
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function dir(): string
    {
        return self::$current === 'ar' ? 'rtl' : 'ltr';
    }

    public static function get(string $key, array $params = []): string
    {
        $text = self::$strings[$key] ?? self::$fallback[$key] ?? $key;
        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }

    /** Strings needed by JavaScript (chart labels etc.). */
    public static function forJs(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }
}
