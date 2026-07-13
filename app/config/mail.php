<?php
/**
 * Mail / SMTP configuration.
 */
return [
    'driver'    => getenv('MAIL_DRIVER') ?: 'log',
    'host'      => getenv('MAIL_HOST') ?: 'localhost',
    'port'      => (int) (getenv('MAIL_PORT') ?: 1025),
    'username'  => getenv('MAIL_USERNAME') ?: '',
    'password'  => getenv('MAIL_PASSWORD') ?: '',
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@matrimony.local',
    'from_name'    => getenv('MAIL_FROM_NAME') ?: 'Matrimony',
];
