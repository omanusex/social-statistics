<?php
// Application settings. Change the database values to match your MySQL server.
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'social_stats',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'timezone' => 'Asia/Muscat',
        'debug'    => true,          // set to false when the site is deployed
        'default_lang' => 'en',
    ],
    'upload' => [
        'max_bytes'  => 5 * 1024 * 1024,
        'extensions' => ['csv', 'xlsx'],
    ],
    'security' => [
        'max_failed_logins' => 5,
        'lock_minutes'      => 15,
    ],
];
