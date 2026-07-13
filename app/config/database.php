<?php
/**
 * Database configuration.
 * Connection is established lazily by Matrimony\Database\Connection.
 */
return [
    'host'      => getenv('DB_HOST'),
    'port'      => (int) getenv('DB_PORT'),
    'database'  => getenv('DB_DATABASE'),
    'username'  => getenv('DB_USERNAME'),
    'password'  => getenv('DB_PASSWORD'),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
