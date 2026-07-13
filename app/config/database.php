<?php
/**
 * Database configuration.
 * Connection is established lazily by Matrimony\Database\Connection.
 */
return [
    'host'      => getenv('DB_HOST') ?: 'localhost',
    'port'      => (int) (getenv('DB_PORT') ?: 3306),
    'database'  => getenv('DB_DATABASE') ?: 'matrimony',
    'username'  => getenv('DB_USERNAME') ?: 'matrimony_user',
    'password'  => getenv('DB_PASSWORD') ?: 'matrimony_pass',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
