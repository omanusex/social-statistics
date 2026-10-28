<?php
/**
 * Download an empty import template (the database folder itself is not public).
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$files = ['stats' => 'stats_template.csv', 'survey' => 'survey_template.csv'];
$name = $files[input('name')] ?? null;
if (!$name) {
    http_response_code(404);
    exit;
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
readfile(ROOT_PATH . '/database/templates/' . $name);
