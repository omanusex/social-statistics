<?php
// Loaded at the top of every page: settings, session, class autoloading and helpers.

define('ROOT_PATH', dirname(__DIR__));
define('STORAGE_PATH', ROOT_PATH . '/storage');

$config = require __DIR__ . '/config.php';

date_default_timezone_set($config['app']['timezone']);
error_reporting(E_ALL);
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');

spl_autoload_register(function (string $class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_name('SOCIALSTATS');
session_start();

Database::configure($config['db']);
Lang::init($config['app']['default_lang']);

require __DIR__ . '/helpers.php';

// Security headers sent with every page
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// Send visitors to the installer until the database exists
if (!defined('SKIP_INSTALL_CHECK') && !is_file(STORAGE_PATH . '/installed.lock')) {
    redirect('install.php');
}
