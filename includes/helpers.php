<?php
// Small helper functions used by the pages and templates.

/** Escape text for safe HTML output (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Translate a key into the current language. */
function t(string $key, array $params = []): string
{
    return Lang::get($key, $params);
}

/** URL path of the project folder, e.g. "/social-statistics". */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $root    = str_replace('\\', '/', (string) realpath(ROOT_PATH));
        $base    = ($docRoot !== '' && str_starts_with(strtolower($root), strtolower($docRoot)))
            ? rtrim(substr($root, strlen($docRoot)), '/')
            : '';
    }
    return $base;
}

function url(string $path = '', array $query = []): string
{
    $url = base_path() . '/' . ltrim($path, '/');
    return $query ? $url . '?' . http_build_query($query) : $url;
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . $path;
    $version = is_file($file) ? filemtime($file) : 0;
    return url('assets/' . $path) . '?v=' . $version;
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, '/') ? $path : url($path)));
    exit;
}

/** Same page URL with the language switched. */
function lang_switch_url(string $lang): string
{
    $query = $_GET;
    $query['lang'] = $lang;
    return strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($query);
}

// ---- CSRF protection --------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit(t('error.csrf'));
    }
}

// ---- Flash messages ---------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ---- Formatting -------------------------------------------------------------

function fmt_number($value, int $decimals = 0): string
{
    return $value === null ? '–' : number_format((float) $value, $decimals);
}

function fmt_value($value, string $unit): string
{
    if ($value === null) {
        return '–';
    }
    if ($unit === '%') {
        return number_format((float) $value, 1) . '%';
    }
    $value = (float) $value;
    if ($value >= 1_000_000) {
        return number_format($value / 1_000_000, 1) . 'M';
    }
    return number_format($value);
}

/** "2026-09-01" -> "Sep 2026" (or Arabic month name). */
function fmt_month(?string $date): string
{
    if (!$date) {
        return '–';
    }
    $ts = strtotime($date);
    $month = (int) date('n', $ts);
    return t('month.' . $month) . ' ' . date('Y', $ts);
}

function country_name(array $country): string
{
    return Lang::current() === 'ar' ? $country['name_ar'] : $country['name_en'];
}

function metric_name(array $metric): string
{
    return Lang::current() === 'ar' ? $metric['name_ar'] : $metric['name_en'];
}

function metric_description(array $metric): string
{
    return (string) (Lang::current() === 'ar' ? $metric['description_ar'] : $metric['description_en']);
}

/** Read a GET parameter as a trimmed string. */
function input(string $name, string $default = ''): string
{
    $value = $_GET[$name] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** A period from the query string ("2025-03" or "2025"); anything else gives the default. */
function input_period(string $name, string $default, bool $monthly): string
{
    $value = input($name);
    return preg_match($monthly ? '/^\d{4}-\d{2}$/' : '/^\d{4}$/', $value) ? $value : $default;
}

/** JSON that is safe to print inside a <script> block. */
function json_for_script($data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function json_response($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
